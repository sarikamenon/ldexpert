<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Services;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\InvoiceFilterDTO;
use App\DTOs\ResendInvoiceEmailDTO;
use App\DTOs\SendInvoiceDTO;
use App\Enums\InvoiceEmailType;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $repository,
        private readonly CompanyInfoService $companyInfoService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly LedgerService $ledgerService,
    ) {}

    public function generateInvoice(User $user, CreateInvoiceDTO $dto): Invoice
    {
        return DB::transaction(function () use ($dto): Invoice {
            $school = $this->schoolRepository->find($dto->schoolId);
            if (! $school) {
                throw new \InvalidArgumentException('School/family not found.');
            }

            $invoiceNumber = ! empty($dto->invoiceNumber) ? $dto->invoiceNumber : $this->repository->generateInvoiceNumber();
            $schoolSnapshot = $this->copySchoolSnapshot($school);
            $companySnapshot = $this->copyCompanySnapshot();

            if (empty($dto->sessionLogIds)) {
                return $this->createDraftWithoutSessions(
                    $dto,
                    $invoiceNumber,
                    $schoolSnapshot,
                    $companySnapshot
                );
            }

            $sessionLogs = $this->repository->getApprovedSessionLogsForInvoice($dto->sessionLogIds);
            if ($sessionLogs->isEmpty()) {
                throw new \InvalidArgumentException('No eligible session logs found for invoice generation.');
            }

            $invalidSessions = $sessionLogs->filter(fn ($log) => $log->school_id !== $dto->schoolId);
            if ($invalidSessions->isNotEmpty()) {
                throw new \InvalidArgumentException('All selected session logs must belong to the selected school.');
            }

            $totals = $this->calculateTotals($sessionLogs);

            $invoice = $this->repository->create([
                'school_id' => $dto->schoolId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $dto->invoiceDate,
                'billing_period_start' => $dto->billingPeriodStart,
                'billing_period_end' => $dto->billingPeriodEnd,
                'status' => InvoiceStatus::DRAFT->value,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => $dto->notes,
                ...$schoolSnapshot,
                ...$companySnapshot,
            ]);

            $this->repository->linkSessionLogs($invoice, $sessionLogs->pluck('id')->toArray());

            return $invoice->load(['sessionLogs.student', 'sessionLogs.service', 'sessionLogs.therapist']);
        });
    }

    /**
     * @param  array<string, string|null>  $schoolSnapshot
     * @param  array<string, string|null>  $companySnapshot
     */
    private function createDraftWithoutSessions(
        CreateInvoiceDTO $dto,
        string $invoiceNumber,
        array $schoolSnapshot,
        array $companySnapshot
    ): Invoice {
        $invoice = $this->repository->create([
            'school_id' => $dto->schoolId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $dto->invoiceDate,
            'billing_period_start' => $dto->billingPeriodStart,
            'billing_period_end' => $dto->billingPeriodEnd,
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => $dto->notes,
            ...$schoolSnapshot,
            ...$companySnapshot,
        ]);

        return $invoice->load(['sessionLogs']);
    }

    /**
     * @param  Collection<int, SessionLog>  $sessionLogs
     * @return array<string, float>
     */
    public function calculateTotals(Collection $sessionLogs): array
    {
        $subtotal = $sessionLogs->sum('school_invoice_amount');
        $taxTotal = 0; // Tax calculation can be extended later
        $total = $subtotal + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function copySchoolSnapshot(School $school): array
    {
        return [
            'school_name' => $school->full_name,
            'school_display_name' => $school->display_name,
            'school_address' => $school->address,
            'school_state' => $school->getRawOriginal('state') ?? $school->stateCode,
            'school_contact_first_name' => $school->contact_first_name,
            'school_contact_last_name' => $school->contact_last_name,
            'school_contact_phone' => $school->contact_phone,
            'school_contact_email' => $school->contact_email,
            'school_invoice_email' => $school->invoice_email,
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

    public function sendInvoice(User $user, Invoice $invoice, SendInvoiceDTO $dto): Invoice
    {
        if ($invoice->isSent() || $invoice->isPaid()) {
            throw new \InvalidArgumentException('Invoice cannot be sent in its current status.');
        }

        return DB::transaction(function () use ($user, $invoice, $dto) {
            // Determine recipient email
            $recipientEmail = $dto->email
                ?? $invoice->school_invoice_email
                ?? $invoice->school_contact_email;

            if (! $recipientEmail) {
                throw new \InvalidArgumentException('No email address available for sending invoice.');
            }

            // Generate payment token for online payment link
            $paymentUrl = null;
            if ((float) $invoice->total > 0) {
                $invoice->ensurePaymentToken();
                $paymentUrl = $invoice->getPaymentUrl();
            }

            // Send email with PDF attachment and payment link
            Mail::to($recipientEmail)->send(new InvoiceMail($invoice, $dto->message, $paymentUrl));

            // Log initial email send
            InvoiceEmailLog::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceEmailType::INITIAL->value,
                'recipient_email' => $recipientEmail,
                'custom_message' => $dto->message,
                'sent_by_id' => $user->id,
                'sent_at' => now(),
            ]);

            // Mark invoice as sent
            $invoice = $this->repository->markAsSent($invoice, $user->id);

            // Create ledger entry for invoice generation
            $this->ledgerService->createInvoiceGeneratedEntry($invoice);

            return $invoice;
        });
    }

    public function resendInvoiceEmail(User $user, Invoice $invoice, ResendInvoiceEmailDTO $dto): void
    {
        if (! $invoice->isSent()) {
            throw new \InvalidArgumentException('Invoice must be in sent status to resend email.');
        }
        if ($invoice->isPaid()) {
            throw new \InvalidArgumentException('Cannot resend email for a paid invoice.');
        }

        // Reuse existing payment token — do NOT regenerate
        $paymentUrl = null;
        if ((float) $invoice->total > 0 && $invoice->payment_token) {
            $paymentUrl = $invoice->getPaymentUrl();
        }

        Mail::to($dto->email)->send(new InvoiceMail($invoice, $dto->message, $paymentUrl));

        InvoiceEmailLog::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceEmailType::RESEND->value,
            'recipient_email' => $dto->email,
            'custom_message' => $dto->message,
            'sent_by_id' => $user->id,
            'sent_at' => now(),
        ]);
    }

    public function find(int $id): ?Invoice
    {
        return $this->repository->find($id);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Support\Collection<int, Invoice>}
     */
    public function listForDataTables(InvoiceFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    public function attachSessionsToDraft(Invoice $invoice, AttachSessionsDTO $dto): Invoice
    {
        if (! $invoice->isDraft()) {
            throw new \InvalidArgumentException('Sessions can only be attached to draft invoices.');
        }

        return DB::transaction(function () use ($invoice, $dto): Invoice {
            $this->repository->unlinkAllSessionsForInvoice($invoice);

            if (empty($dto->sessionLogIds)) {
                $this->repository->updateTotals($invoice, 0, 0, 0);

                return $invoice->refresh();
            }

            $sessionLogs = $this->repository->getSessionLogsForInvoiceUpdate($invoice, $dto->sessionLogIds);
            $requestedIds = $dto->sessionLogIds;
            $foundIds = $sessionLogs->pluck('id')->all();
            $missing = array_diff($requestedIds, $foundIds);
            if (! empty($missing)) {
                throw new \InvalidArgumentException(
                    'Some selected session logs are not eligible (wrong school, not approved, or already on another invoice).'
                );
            }

            $this->repository->linkSessionLogs($invoice, $foundIds);
            $totals = $this->calculateTotals($sessionLogs);
            $this->repository->updateTotals($invoice, $totals['subtotal'], $totals['tax_total'], $totals['total']);

            return $invoice->refresh()->load(['sessionLogs.student', 'sessionLogs.service', 'sessionLogs.therapist']);
        });
    }
}
