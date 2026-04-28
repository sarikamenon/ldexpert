<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Services\CompanyInfoService;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateTherapistBillDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SendTherapistBillDTO;
use App\DTOs\TherapistBillFilterDTO;
use App\Enums\TherapistBillStatus;
use App\Mail\TherapistBillMail;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class TherapistBillService
{
    public function __construct(
        private readonly TherapistBillRepositoryInterface $repository,
        private readonly CompanyInfoService $companyInfoService,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, TherapistBill>}
     */
    public function listForDataTables(TherapistBillFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    public function generateBill(User $user, CreateTherapistBillDTO $dto): TherapistBill
    {
        return DB::transaction(function () use ($dto): TherapistBill {
            // Get therapist for snapshot
            $therapist = User::with('therapistProfile')->find($dto->therapistId);
            if (! $therapist) {
                throw new \InvalidArgumentException('Therapist not found.');
            }

            // Generate bill number if not provided or empty
            $billNumber = ! empty($dto->billNumber) ? $dto->billNumber : $this->repository->generateBillNumber();

            // Copy snapshots
            $therapistSnapshot = $this->copyTherapistSnapshot($therapist);
            $companySnapshot = $this->copyCompanySnapshot();

            // Determine due date
            $dueDate = $dto->dueDate ?? now()->addDays(30)->toDateString();

            $sessionLogs = $dto->sessionLogIds !== []
                ? $this->repository->getApprovedSessionLogsForBilling($dto->sessionLogIds)
                : collect();

            if ($sessionLogs->isNotEmpty()) {
                // Validate all session logs belong to the selected therapist
                $invalidSessions = $sessionLogs->filter(fn ($log) => $log->therapist_id !== $dto->therapistId);
                if ($invalidSessions->isNotEmpty()) {
                    throw new \InvalidArgumentException('All selected session logs must belong to the selected therapist.');
                }
            }

            // Calculate totals (0 when there are no sessions)
            $totals = $sessionLogs->isEmpty()
                ? [
                    'subtotal' => 0.0,
                    'adjustments_total' => 0.0,
                    'total_due' => 0.0,
                ]
                : $this->calculateTotals($sessionLogs);

            // Create bill (DRAFT-first, sessions can be attached later)
            $bill = $this->repository->create([
                'therapist_id' => $dto->therapistId,
                'bill_number' => $billNumber,
                'bill_date' => $dto->billDate,
                'billing_period_start' => $dto->billingPeriodStart,
                'billing_period_end' => $dto->billingPeriodEnd,
                'status' => TherapistBillStatus::DRAFT->value,
                'subtotal' => $totals['subtotal'],
                'adjustments_total' => $totals['adjustments_total'],
                'total_due' => $totals['total_due'],
                'due_date' => $dueDate,
                'notes' => $dto->notes,
                ...$therapistSnapshot,
                ...$companySnapshot,
            ]);

            if ($sessionLogs->isNotEmpty()) {
                // Link session logs to bill
                $this->repository->linkSessionLogs($bill, $sessionLogs->pluck('id')->toArray());
            }

            $relations = ['sessionLogs.student', 'sessionLogs.service', 'sessionLogs.therapist'];

            return $bill->load($relations);
        });
    }

    /**
     * @param  Collection<int, SessionLog>  $sessionLogs
     * @return array<string, float>
     */
    public function calculateTotals(Collection $sessionLogs): array
    {
        $subtotal = $sessionLogs->sum('therapist_billable_amount');
        $adjustmentsTotal = 0; // Adjustments can be added later
        $totalDue = $subtotal + $adjustmentsTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'adjustments_total' => round($adjustmentsTotal, 2),
            'total_due' => round($totalDue, 2),
        ];
    }

    public function attachSessionsToDraft(TherapistBill $bill, AttachSessionsDTO $dto): TherapistBill
    {
        if (! $bill->isDraft()) {
            throw new \InvalidArgumentException('Sessions can only be attached to draft therapist bills.');
        }

        return DB::transaction(function () use ($bill, $dto): TherapistBill {
            $this->repository->unlinkAllSessionsForTherapistBill($bill);

            if (empty($dto->sessionLogIds)) {
                $this->repository->updateTotals($bill, 0, 0, 0);

                return $bill->refresh()->load([
                    'sessionLogs.student',
                    'sessionLogs.service',
                    'sessionLogs.therapist',
                ]);
            }

            $sessionLogs = $this->repository->getSessionLogsForTherapistBillUpdate($bill, $dto->sessionLogIds);
            $requestedIds = $dto->sessionLogIds;
            $foundIds = $sessionLogs->pluck('id')->all();
            $missing = array_diff($requestedIds, $foundIds);

            if (! empty($missing)) {
                throw new \InvalidArgumentException(
                    'Some selected session logs are not eligible (wrong therapist, not approved, or already on another bill).'
                );
            }

            $this->repository->linkSessionLogs($bill, $foundIds);
            $totals = $this->calculateTotals($sessionLogs);
            $this->repository->updateTotals(
                $bill,
                $totals['subtotal'],
                $totals['adjustments_total'],
                $totals['total_due'],
            );

            return $bill->refresh()->load([
                'sessionLogs.student',
                'sessionLogs.service',
                'sessionLogs.therapist',
            ]);
        });
    }

    /**
     * @return array<string, string|null>
     */
    public function copyTherapistSnapshot(User $therapist): array
    {
        $profile = $therapist->therapistProfile;

        return [
            'therapist_name' => $therapist->name,
            'therapist_email' => $profile->personal_email ?? $therapist->email,
            'therapist_phone' => $profile?->phone,
            'therapist_address' => $profile?->address,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function copyCompanySnapshot(): array
    {
        $companyInfo = $this->companyInfoService->getCompanyInfo();

        return [
            'company_name' => $companyInfo['name'],
            'company_address' => $companyInfo['address'],
            'company_phone' => $companyInfo['phone'],
            'company_email' => $companyInfo['email'],
            'company_tax_id' => $companyInfo['tax_id'],
        ];
    }

    public function sendBill(User $user, TherapistBill $bill, SendTherapistBillDTO $dto): TherapistBill
    {
        if ($bill->isSent() || $bill->isPaid()) {
            throw new \InvalidArgumentException('Bill cannot be sent in its current status.');
        }

        if ($bill->isZeroAmount()) {
            throw new \InvalidArgumentException('Zero amount bills cannot be sent.');
        }

        return DB::transaction(function () use ($user, $bill, $dto) {
            // Determine recipient email
            $recipientEmail = $dto->email
                ?? $bill->therapist_email
                ?? $bill->therapist?->email;

            if (! $recipientEmail) {
                throw new \InvalidArgumentException('No email address available for sending bill.');
            }

            // Send email with PDF attachment
            try {
                Mail::to($recipientEmail)->send(new TherapistBillMail($bill, $dto->message));
            } catch (\Throwable $e) {
                Log::error('TherapistBillService: failed to send bill email', [
                    'bill_id' => $bill->id,
                    'email' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Mark bill as sent
            $bill = $this->repository->markAsSent($bill, $user->id);

            // Create ledger entry for bill generation
            $this->ledgerService->createBillGeneratedEntry($bill);

            return $bill;
        });
    }

    public function deleteBill(TherapistBill $bill): void
    {
        if (! $bill->isDraft() && ! $bill->isZeroAmount()) {
            throw new \InvalidArgumentException('Only draft or zero amount bills can be deleted.');
        }

        DB::transaction(function () use ($bill): void {
            $this->repository->delete($bill);
        });
    }

    public function find(int $id): ?TherapistBill
    {
        return $this->repository->find($id);
    }
}
