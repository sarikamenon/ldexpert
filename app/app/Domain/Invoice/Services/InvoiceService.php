<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Services;

use App\Domain\Billing\Repositories\InvoiceLineItemRepositoryInterface;
use App\Domain\Billing\Services\AdvanceChargeLineBuilder;
use App\Domain\Billing\Services\BillingScheduleService;
use App\Domain\Billing\Services\BillingSettingsService;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\InvoiceFilterDTO;
use App\DTOs\InvoiceLineItemDTO;
use App\DTOs\ResendInvoiceEmailDTO;
use App\DTOs\SendInvoiceDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\InvoiceEmailType;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\InvoiceLineItem;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $repository,
        private readonly CompanyInfoService $companyInfoService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly LedgerService $ledgerService,
        private readonly BillingScheduleService $billingScheduleService,
        private readonly InvoiceLineItemRepositoryInterface $lineItemRepository,
        private readonly AdvanceChargeLineBuilder $chargeLineBuilder,
        private readonly BillingSettingsService $billingSettingsService,
    ) {}

    public function generateInvoice(User $user, CreateInvoiceDTO $dto): Invoice
    {
        return DB::transaction(function () use ($dto): Invoice {
            $school = $this->schoolRepository->find($dto->schoolId);
            if (! $school) {
                throw new \InvalidArgumentException('School/family not found.');
            }

            // Advance (prepaid) schools bill from selectable schedules, not session
            // logs, and produce an advance-mode invoice (§6).
            if ($this->billingScheduleService->resolveSchoolBillingMode($school) === BillingMode::ADVANCE) {
                return $this->generateAdvanceInvoice($dto, $school);
            }

            $invoiceNumber = ! empty($dto->invoiceNumber) ? $dto->invoiceNumber : $this->repository->generateInvoiceNumber();
            $schoolSnapshot = $this->copySchoolSnapshot($school);
            $companySnapshot = $this->copyCompanySnapshot();

            if (empty($dto->sessionLogIds)) {
                return $this->createDraftWithoutSessions(
                    $dto,
                    $school,
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
                'due_date' => $this->resolveDueDate($dto, $school),
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
        School $school,
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
            'due_date' => $this->resolveDueDate($dto, $school),
            'notes' => $dto->notes,
            ...$schoolSnapshot,
            ...$companySnapshot,
        ]);

        return $invoice->load(['sessionLogs']);
    }

    /**
     * Generate a manual advance (prepaid) invoice from selectable schedules.
     *
     * Mirrors the automated advance path: charge lines only (no prior-period
     * adjustments, per Q10), billing_mode = advance, and schedules.invoice_id
     * stamped (§5) so the generator never re-charges them. Reuses the shared
     * AdvanceChargeLineBuilder so manual and automated amounts match. The due
     * date follows the school's configured payment terms, and the school's
     * BillingSchedule tracking is advanced like advanceSchedule() (Q6) so the
     * automated next run reconciles this invoice and bills the following period.
     *
     * Must run inside the generateInvoice() transaction.
     */
    private function generateAdvanceInvoice(CreateInvoiceDTO $dto, School $school): Invoice
    {
        $invoiceNumber = ! empty($dto->invoiceNumber) ? $dto->invoiceNumber : $this->repository->generateInvoiceNumber();
        $schoolSnapshot = $this->copySchoolSnapshot($school);
        $companySnapshot = $this->copyCompanySnapshot();

        $periodStart = Carbon::parse($dto->billingPeriodStart);
        $periodEnd = Carbon::parse($dto->billingPeriodEnd);

        $schedule = $this->billingScheduleService->getEntityConfig(
            School::class,
            $dto->schoolId,
            BillingScheduleType::SCHOOL_INVOICE->value,
        );
        $paymentTermsDays = $this->resolveAdvancePaymentTermsDays($schedule);

        // Reuse the shared charge-line builder (notYetInvoiced + rate logic),
        // then keep only the admin-selected schedules.
        $chargeLines = $this->chargeLineBuilder
            ->build($dto->schoolId, $periodStart, $periodEnd)
            ->when(
                $dto->scheduleIds !== [],
                fn (Collection $lines): Collection => $lines->filter(
                    fn (InvoiceLineItemDTO $line): bool => in_array($line->scheduleId, $dto->scheduleIds, true)
                )->values(),
            );

        $subtotal = round((float) $chargeLines->sum(fn (InvoiceLineItemDTO $line): float => $line->total), 2);

        $invoice = $this->repository->create([
            'school_id' => $dto->schoolId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $dto->invoiceDate,
            'billing_period_start' => $dto->billingPeriodStart,
            'billing_period_end' => $dto->billingPeriodEnd,
            'billing_mode' => BillingMode::ADVANCE->value,
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => $subtotal,
            'tax_total' => 0,
            'total' => $subtotal,
            'due_date' => $dto->dueDate
                ?? Carbon::parse($dto->invoiceDate)->addDays($paymentTermsDays)->toDateString(),
            'notes' => $dto->notes,
            ...$schoolSnapshot,
            ...$companySnapshot,
        ]);

        if ($chargeLines->isNotEmpty()) {
            $sortOrder = 0;
            $this->lineItemRepository->createMany(
                $invoice,
                $chargeLines->map(function (InvoiceLineItemDTO $line) use (&$sortOrder): array {
                    return [
                        ...$line->toArray(),
                        'sort_order' => $sortOrder++,
                    ];
                })->all()
            );

            $this->stampSchedulesOnInvoice($chargeLines, $invoice);
        }

        // Advance the schedule's tracking so the automated next run reconciles
        // this manual invoice and bills the following period (Q6).
        if ($schedule !== null) {
            $this->billingScheduleService->advanceSchedule($schedule, $periodEnd);
        }

        return $invoice->load(['lineItems', 'sessionLogs']);
    }

    /**
     * Payment terms for an advance invoice: the school's BillingSchedule value,
     * or the advance settings default when no schedule exists (pre-§4 schools).
     */
    private function resolveAdvancePaymentTermsDays(?BillingSchedule $schedule): int
    {
        if ($schedule !== null) {
            return (int) $schedule->payment_terms_days;
        }

        return $this->billingSettingsService->getSettings()->advance_default_payment_terms_days;
    }

    /**
     * Stamp invoice_id on every schedule that became an ADVANCE_SCHEDULED line (§5).
     *
     * @param  Collection<int, InvoiceLineItemDTO>  $chargeLines
     */
    private function stampSchedulesOnInvoice(Collection $chargeLines, Invoice $invoice): void
    {
        $scheduleIds = $chargeLines
            ->map(fn (InvoiceLineItemDTO $line): ?int => $line->scheduleId)
            ->filter()
            ->values()
            ->all();

        if ($scheduleIds === []) {
            return;
        }

        Schedule::query()
            ->whereIn('id', $scheduleIds)
            ->update(['invoice_id' => $invoice->id]);
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
     * Resolve the invoice due date: honour the user-supplied date when present,
     * otherwise derive it from the invoice date plus the school's effective
     * payment-terms days (its school_invoice schedule, else the mode-specific
     * billing-settings default). Only standard-mode invoices reach this — the
     * advance path resolves its own due date.
     */
    private function resolveDueDate(CreateInvoiceDTO $dto, School $school): string
    {
        if ($dto->dueDate !== null) {
            return Carbon::parse($dto->dueDate)->toDateString();
        }

        return Carbon::parse($dto->invoiceDate)
            ->addDays($this->billingScheduleService->resolveSchoolPaymentTermsDays($school))
            ->toDateString();
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

        if ($invoice->isZeroAmount()) {
            throw new \InvalidArgumentException('Zero amount invoices cannot be sent.');
        }

        return DB::transaction(function () use ($user, $invoice, $dto) {
            // Determine recipient email — invoice email only, no contact-email fallback
            $recipientEmail = $dto->email
                ?? $invoice->school_invoice_email;

            if (! $recipientEmail) {
                throw new \InvalidArgumentException('No invoice email address available for sending invoice.');
            }

            // Generate payment token for online payment link (private-student schools only)
            $paymentUrl = null;
            if ((float) $invoice->total > 0 && $invoice->allowsOnlinePayment()) {
                $invoice->ensurePaymentToken();
                $paymentUrl = $invoice->getPaymentUrl();
            }

            // Send email with PDF attachment and payment link
            try {
                Mail::to($recipientEmail)->send(new InvoiceMail($invoice, $dto->message, $paymentUrl));
            } catch (\Throwable $e) {
                Log::error('InvoiceService: failed to send invoice email', [
                    'invoice_id' => $invoice->id,
                    'email' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

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
        if ($invoice->isZeroAmount()) {
            throw new \InvalidArgumentException('Zero amount invoices cannot be sent.');
        }

        // Reuse existing payment token — do NOT regenerate (private-student schools only)
        $paymentUrl = null;
        if ((float) $invoice->total > 0 && $invoice->payment_token && $invoice->allowsOnlinePayment()) {
            $paymentUrl = $invoice->getPaymentUrl();
        }

        try {
            Mail::to($dto->email)->send(new InvoiceMail($invoice, $dto->message, $paymentUrl));
        } catch (\Throwable $e) {
            Log::error('InvoiceService: failed to resend invoice email', [
                'invoice_id' => $invoice->id,
                'email' => $dto->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

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

        // Advance invoices re-select schedules, not session logs.
        if ($invoice->isAdvanceMode()) {
            return $this->attachSchedulesToAdvanceDraft($invoice, $dto);
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

    /**
     * Assemble the selectable-schedule rows for an advance draft's attach page.
     *
     * Candidate schedules = not-yet-invoiced schedules in the period (with their
     * computed charge amount) plus the schedules already on this invoice. Returns
     * display rows and the currently-attached schedule ids for pre-checking.
     *
     * @return array{rows: Collection<int, array{id: int, date: string, student: string, service: string, therapist: string, duration: string, amount: float, amountFormatted: string, attached: bool}>, attachedScheduleIds: array<int, int>, periodLabel: string}
     */
    public function getAdvanceAttachData(Invoice $invoice): array
    {
        $schoolId = (int) $invoice->school_id;
        $periodStart = $invoice->billing_period_start->copy();
        $periodEnd = $invoice->billing_period_end->copy();

        // Amounts for not-yet-invoiced schedules in the period, keyed by schedule id.
        // ADVANCE_SCHEDULED lines always carry a schedule id; guard the nullable
        // type so a null key cannot collapse unrelated amounts together.
        $amountByScheduleId = $this->chargeLineBuilder
            ->build($schoolId, $periodStart, $periodEnd)
            ->filter(fn (InvoiceLineItemDTO $line): bool => $line->scheduleId !== null)
            ->mapWithKeys(fn (InvoiceLineItemDTO $line): array => [$line->scheduleId => $line->total]);

        // Already-attached schedules are excluded from the builder (they are no
        // longer notYetInvoiced), so source their amount from this invoice's
        // existing line items instead of defaulting to 0.
        $existingLineAmounts = $this->lineItemRepository
            ->getForInvoice($invoice->id)
            ->filter(fn (InvoiceLineItem $line): bool => $line->schedule_id !== null)
            ->mapWithKeys(fn (InvoiceLineItem $line): array => [$line->schedule_id => (float) $line->total]);

        $amountByScheduleId = $amountByScheduleId->union($existingLineAmounts);

        /** @var Collection<int, Schedule> $attached */
        $attached = Schedule::query()
            ->forInvoice($invoice->id)
            ->with(['student', 'service', 'therapist'])
            ->orderBy('schedule_date')
            ->get();

        /** @var Collection<int, Schedule> $available */
        $available = Schedule::query()
            ->where('school_id', $schoolId)
            ->betweenScheduleDates($periodStart->toDateString(), $periodEnd->toDateString())
            ->scheduled()
            ->notYetInvoiced()
            ->with(['student', 'service', 'therapist'])
            ->orderBy('schedule_date')
            ->get();

        $attachedIds = $attached->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $dateFormat = (string) config('display.date');

        $rows = $attached
            ->concat($available)
            ->unique('id')
            ->map(function (Schedule $schedule) use ($amountByScheduleId, $attachedIds, $dateFormat): array {
                $amount = (float) ($amountByScheduleId->get($schedule->id) ?? 0.0);

                return [
                    'id' => (int) $schedule->id,
                    'date' => $schedule->schedule_date->format($dateFormat),
                    'student' => $schedule->student->name ?? '—',
                    'service' => $schedule->service->name ?? '—',
                    'therapist' => $schedule->therapist->name ?? '—',
                    'duration' => $schedule->durationMinutes().' min',
                    'amount' => $amount,
                    'amountFormatted' => '$'.number_format($amount, 2),
                    'attached' => in_array($schedule->id, $attachedIds, true),
                ];
            })
            ->values();

        $periodLabel = $periodStart->format($dateFormat).' – '.$periodEnd->format($dateFormat);

        return [
            'rows' => $rows,
            'attachedScheduleIds' => $attachedIds,
            'periodLabel' => $periodLabel,
        ];
    }

    /**
     * Re-select the schedules on an advance draft invoice (unlink-all-then-relink).
     *
     * Clears schedules.invoice_id for the whole prior set (§5 detach-clear),
     * rebuilds ADVANCE_SCHEDULED charge lines for the new selection, re-stamps,
     * and recomputes totals. Charge lines only (Q10).
     */
    private function attachSchedulesToAdvanceDraft(Invoice $invoice, AttachSessionsDTO $dto): Invoice
    {
        return DB::transaction(function () use ($invoice, $dto): Invoice {
            // Detach the entire prior set so a removed schedule becomes billable again.
            Schedule::query()->forInvoice($invoice->id)->update(['invoice_id' => null]);
            $this->lineItemRepository->deleteForInvoice($invoice->id);

            if ($dto->scheduleIds === []) {
                $this->repository->updateTotals($invoice, 0, 0, 0);

                return $invoice->refresh()->load(['lineItems', 'sessionLogs']);
            }

            $periodStart = $invoice->billing_period_start->copy();
            $periodEnd = $invoice->billing_period_end->copy();

            $schoolId = (int) $invoice->school_id;

            $chargeLines = $this->chargeLineBuilder
                ->build($schoolId, $periodStart, $periodEnd)
                ->filter(fn (InvoiceLineItemDTO $line): bool => in_array($line->scheduleId, $dto->scheduleIds, true))
                ->values();

            $sortOrder = 0;
            $this->lineItemRepository->createMany(
                $invoice,
                $chargeLines->map(function (InvoiceLineItemDTO $line) use (&$sortOrder): array {
                    return [
                        ...$line->toArray(),
                        'sort_order' => $sortOrder++,
                    ];
                })->all()
            );

            $this->stampSchedulesOnInvoice($chargeLines, $invoice);

            $subtotal = round((float) $chargeLines->sum(fn (InvoiceLineItemDTO $line): float => $line->total), 2);
            $this->repository->updateTotals($invoice, $subtotal, 0, $subtotal);

            return $invoice->refresh()->load(['lineItems', 'sessionLogs']);
        });
    }
}
