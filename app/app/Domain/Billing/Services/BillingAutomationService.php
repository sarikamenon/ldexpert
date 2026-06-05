<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Repositories\BillingScheduleRepositoryInterface;
use App\Domain\Billing\Repositories\InvoiceLineItemRepositoryInterface;
use App\Domain\Invoice\Services\InvoiceService;
use App\DTOs\BillingRunResultDTO;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\CreateTherapistBillDTO;
use App\DTOs\SendInvoiceDTO;
use App\DTOs\SendTherapistBillDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleRunStatus;
use App\Enums\InvoiceLineType;
use App\Enums\SessionLogStatus;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use App\Models\Invoice;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class BillingAutomationService
{
    public function __construct(
        private readonly BillingScheduleRepositoryInterface $scheduleRepository,
        private readonly InvoiceLineItemRepositoryInterface $lineItemRepository,
        private readonly InvoiceService $invoiceService,
        private readonly TherapistBillService $therapistBillService,
        private readonly BillingScheduleService $scheduleService,
        private readonly AdvanceBillingService $advanceBillingService,
    ) {}

    /**
     * Process all due billing schedules.
     *
     * @return Collection<int, BillingRunResultDTO>
     */
    public function processAllDueSchedules(string $type = 'all', bool $dryRun = false): Collection
    {
        $schedules = $this->scheduleRepository->getDueSchedules($type);

        /** @var Collection<int, BillingRunResultDTO> $results */
        $results = collect();

        foreach ($schedules as $schedule) {
            try {
                $result = $this->processSingleSchedule($schedule, $dryRun);
                $results->push($result);
            } catch (\Throwable $e) {
                Log::error('Billing schedule run failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);

                $result = $this->buildFailedResult($schedule, $e->getMessage());
                $results->push($result);

                if (! $dryRun) {
                    $this->logRun($schedule, $result);
                }
            }
        }

        return $results;
    }

    /**
     * Process a single billing schedule.
     */
    public function processSingleSchedule(BillingSchedule $schedule, bool $dryRun = false): BillingRunResultDTO
    {
        if ($schedule->billing_mode === BillingMode::ADVANCE) {
            return $this->advanceBillingService->processAdvanceSchedule($schedule, $dryRun);
        }

        return $this->processStandardSchedule($schedule, $dryRun);
    }

    /**
     * Process a standard (post-delivery) billing schedule.
     */
    private function processStandardSchedule(BillingSchedule $schedule, bool $dryRun): BillingRunResultDTO
    {
        $period = $this->resolveCurrentPeriod($schedule);
        $periodStart = $period['start'];
        $periodEnd = $period['end'];

        if ($schedule->isForSchool()) {
            return $this->processSchoolInvoice($schedule, $periodStart, $periodEnd, $dryRun);
        }

        if ($schedule->isForTherapist()) {
            return $this->processTherapistBill($schedule, $periodStart, $periodEnd, $dryRun);
        }

        throw new \InvalidArgumentException("Unsupported standard schedule type: {$schedule->schedule_type->value}");
    }

    /**
     * Generate a school invoice from approved, un-invoiced sessions.
     */
    private function processSchoolInvoice(
        BillingSchedule $schedule,
        Carbon $periodStart,
        Carbon $periodEnd,
        bool $dryRun,
    ): BillingRunResultDTO {
        $schoolId = $schedule->schedulable_id;

        $sessions = $this->sweepUnInvoicedSessions($schoolId, $periodEnd);

        if ($sessions->isEmpty()) {
            $result = $this->buildSkippedResult($schedule, $periodStart, $periodEnd);

            if (! $dryRun) {
                $this->logRun($schedule, $result);
                $this->scheduleService->advanceSchedule($schedule, $periodEnd);
            }

            return $result;
        }

        if ($dryRun) {
            return $this->buildDryRunResult($schedule, $periodStart, $periodEnd, $sessions);
        }

        [$result, $invoice, $run] = DB::transaction(function () use ($schedule, $periodStart, $periodEnd, $sessions, $schoolId): array {
            $sessionsFromPrior = $sessions->filter(
                fn (SessionLog $s): bool => $s->session_date->lt($periodStart)
            )->count();

            $adminUser = $this->getSystemUser();

            $invoice = $this->invoiceService->generateInvoice($adminUser, CreateInvoiceDTO::fromArray([
                'school_id' => $schoolId,
                'invoice_date' => now()->toDateString(),
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'session_log_ids' => $sessions->pluck('id')->all(),
            ]));

            $this->createStandardLineItems($invoice->id, $sessions, $periodStart, $periodEnd);

            $result = new BillingRunResultDTO(
                billingScheduleId: $schedule->id,
                status: BillingScheduleRunStatus::SUCCESS->value,
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                sessionsFound: $sessions->count(),
                sessionsFromPriorPeriods: $sessionsFromPrior,
                adjustmentsCount: 0,
                adjustmentTotal: 0,
                carryForwardAmount: 0,
                invoiceId: $invoice->id,
                therapistBillId: null,
                totalAmount: (float) $invoice->total,
                autoSent: false,
            );

            $run = $this->logRun($schedule, $result);
            $this->scheduleService->advanceSchedule($schedule, $periodEnd);

            return [$result, $invoice, $run];
        });

        // Auto-send happens AFTER the generation transaction commits — a mailer
        // failure must never roll back a successfully generated invoice.
        return $this->maybeAutoSendInvoice($schedule, $invoice, $result, $run);
    }

    /**
     * Generate a therapist bill from approved, un-billed sessions.
     */
    private function processTherapistBill(
        BillingSchedule $schedule,
        Carbon $periodStart,
        Carbon $periodEnd,
        bool $dryRun,
    ): BillingRunResultDTO {
        $therapistId = $schedule->schedulable_id;

        $sessions = $this->sweepUnBilledSessions($therapistId, $periodEnd);

        if ($sessions->isEmpty()) {
            $result = $this->buildSkippedResult($schedule, $periodStart, $periodEnd);

            if (! $dryRun) {
                $this->logRun($schedule, $result);
                $this->scheduleService->advanceSchedule($schedule, $periodEnd);
            }

            return $result;
        }

        if ($dryRun) {
            return $this->buildDryRunResult($schedule, $periodStart, $periodEnd, $sessions);
        }

        [$result, $bill, $run] = DB::transaction(function () use ($schedule, $periodStart, $periodEnd, $sessions, $therapistId): array {
            $sessionsFromPrior = $sessions->filter(
                fn (SessionLog $s): bool => $s->session_date->lt($periodStart)
            )->count();

            $adminUser = $this->getSystemUser();

            $bill = $this->therapistBillService->generateBill($adminUser, CreateTherapistBillDTO::fromArray([
                'therapist_id' => $therapistId,
                'bill_date' => now()->toDateString(),
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'session_log_ids' => $sessions->pluck('id')->all(),
                'due_date' => now()->addDays($schedule->payment_terms_days)->toDateString(),
            ]));

            $result = new BillingRunResultDTO(
                billingScheduleId: $schedule->id,
                status: BillingScheduleRunStatus::SUCCESS->value,
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                sessionsFound: $sessions->count(),
                sessionsFromPriorPeriods: $sessionsFromPrior,
                adjustmentsCount: 0,
                adjustmentTotal: 0,
                carryForwardAmount: 0,
                invoiceId: null,
                therapistBillId: $bill->id,
                totalAmount: (float) $bill->total_due,
                autoSent: false,
            );

            $run = $this->logRun($schedule, $result);
            $this->scheduleService->advanceSchedule($schedule, $periodEnd);

            return [$result, $bill, $run];
        });

        // Auto-send after the transaction commits (mailer failure must not roll back the bill).
        return $this->maybeAutoSendBill($schedule, $bill, $result, $run);
    }

    /**
     * Sweep all approved, un-invoiced sessions for a school up to period end.
     *
     * @return Collection<int, SessionLog>
     */
    public function sweepUnInvoicedSessions(int $schoolId, Carbon $periodEnd): Collection
    {
        /** @var Collection<int, SessionLog> $result */
        $result = SessionLog::query()
            ->where('school_id', $schoolId)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id')
            ->where('session_date', '<=', $periodEnd->toDateString())
            ->where('school_invoice_amount', '>', 0)
            ->with(['student', 'service', 'therapist', 'school'])
            ->orderBy('session_date')
            ->get();

        return $result;
    }

    /**
     * Sweep all approved, un-billed sessions for a therapist up to period end.
     *
     * @return Collection<int, SessionLog>
     */
    public function sweepUnBilledSessions(int $therapistId, Carbon $periodEnd): Collection
    {
        /** @var Collection<int, SessionLog> $result */
        $result = SessionLog::query()
            ->where('therapist_id', $therapistId)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_therapist', true)
            ->whereNull('therapist_bill_id')
            ->where('session_date', '<=', $periodEnd->toDateString())
            ->where('therapist_billable_amount', '>', 0)
            ->with(['student', 'service', 'therapist', 'school'])
            ->orderBy('session_date')
            ->get();

        return $result;
    }

    /**
     * Create SESSION_CHARGE line items for a standard invoice.
     *
     * @param  Collection<int, SessionLog>  $sessions
     */
    private function createStandardLineItems(
        int $invoiceId,
        Collection $sessions,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): void {
        $lineItems = [];
        $sortOrder = 0;

        foreach ($sessions as $session) {
            $serviceName = $session->service->name ?? 'Session';
            $studentName = $session->student->name ?? 'Unknown';
            $date = $session->session_date->format('M j, Y');

            $lineItems[] = [
                'line_type' => InvoiceLineType::SESSION_CHARGE->value,
                'description' => "{$serviceName} — {$studentName} ({$date})",
                'session_log_id' => $session->id,
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'quantity' => 1,
                'unit_price' => (float) $session->school_invoice_amount,
                'total' => (float) $session->school_invoice_amount,
                'sort_order' => $sortOrder++,
            ];
        }

        $invoice = \App\Models\Invoice::findOrFail($invoiceId);
        $this->lineItemRepository->createMany($invoice, $lineItems);
    }

    /**
     * Resolve the billing period for a schedule based on its last run.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveCurrentPeriod(BillingSchedule $schedule): array
    {
        if ($schedule->last_period_end !== null) {
            $nextDay = $schedule->last_period_end->copy()->addDay();

            return $this->scheduleService->determineBillingPeriod($schedule->frequency, $nextDay);
        }

        // First-ever run: anchor the first period on billing_start_date when set,
        // so the first invoice covers the intended period instead of "now".
        $anchor = $schedule->billing_start_date !== null
            ? $schedule->billing_start_date->copy()
            : now();

        return $this->scheduleService->determineBillingPeriod($schedule->frequency, $anchor);
    }

    private function logRun(BillingSchedule $schedule, BillingRunResultDTO $result): BillingScheduleRun
    {
        return $this->scheduleRepository->logRun([
            'billing_schedule_id' => $schedule->id,
            'billing_period_start' => $result->billingPeriodStart,
            'billing_period_end' => $result->billingPeriodEnd,
            'generation_date' => now()->toDateString(),
            'status' => $result->status,
            'sessions_found' => $result->sessionsFound,
            'sessions_from_prior_periods' => $result->sessionsFromPriorPeriods,
            'adjustments_count' => $result->adjustmentsCount,
            'adjustment_total' => $result->adjustmentTotal,
            'carry_forward_amount' => $result->carryForwardAmount,
            'invoice_id' => $result->invoiceId,
            'therapist_bill_id' => $result->therapistBillId,
            'total_amount' => $result->totalAmount,
            'auto_sent' => $result->autoSent,
            'error_message' => $result->errorMessage,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Auto-send a freshly generated school invoice when the schedule opts in.
     *
     * Runs outside the generation transaction. Skips zero-amount invoices (the
     * send path rejects them anyway) and swallows mailer failures — a send
     * failure must never undo a successfully generated invoice. On success the
     * run row's auto_sent flag is updated to reflect what actually happened.
     */
    private function maybeAutoSendInvoice(BillingSchedule $schedule, Invoice $invoice, BillingRunResultDTO $result, BillingScheduleRun $run): BillingRunResultDTO
    {
        if (! $schedule->auto_send || (float) $invoice->total <= 0) {
            return $result;
        }

        try {
            $this->invoiceService->sendInvoice($this->getSystemUser(), $invoice, new SendInvoiceDTO);
            $run->update(['auto_sent' => true]);

            return $result->withAutoSent(true);
        } catch (\Throwable $e) {
            Log::error('Billing auto-send invoice failed', [
                'schedule_id' => $schedule->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }
    }

    /**
     * Auto-send a freshly generated therapist bill when the schedule opts in.
     * Same guarantees as maybeAutoSendInvoice().
     */
    private function maybeAutoSendBill(BillingSchedule $schedule, TherapistBill $bill, BillingRunResultDTO $result, BillingScheduleRun $run): BillingRunResultDTO
    {
        if (! $schedule->auto_send || (float) $bill->total_due <= 0) {
            return $result;
        }

        try {
            $this->therapistBillService->sendBill($this->getSystemUser(), $bill, new SendTherapistBillDTO);
            $run->update(['auto_sent' => true]);

            return $result->withAutoSent(true);
        } catch (\Throwable $e) {
            Log::error('Billing auto-send bill failed', [
                'schedule_id' => $schedule->id,
                'therapist_bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }
    }

    private function getSystemUser(): User
    {
        /** @var User $user */
        $user = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->firstOrFail();

        return $user;
    }

    private function buildSkippedResult(
        BillingSchedule $schedule,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): BillingRunResultDTO {
        return new BillingRunResultDTO(
            billingScheduleId: $schedule->id,
            status: BillingScheduleRunStatus::SKIPPED_NO_SESSIONS->value,
            billingPeriodStart: $periodStart->toDateString(),
            billingPeriodEnd: $periodEnd->toDateString(),
            sessionsFound: 0,
            sessionsFromPriorPeriods: 0,
            adjustmentsCount: 0,
            adjustmentTotal: 0,
            carryForwardAmount: 0,
            invoiceId: null,
            therapistBillId: null,
            totalAmount: null,
            autoSent: false,
        );
    }

    /**
     * @param  Collection<int, SessionLog>  $sessions
     */
    private function buildDryRunResult(
        BillingSchedule $schedule,
        Carbon $periodStart,
        Carbon $periodEnd,
        Collection $sessions,
    ): BillingRunResultDTO {
        $sessionsFromPrior = $sessions->filter(
            fn (SessionLog $s): bool => $s->session_date->lt($periodStart)
        )->count();

        $total = $schedule->isForSchool()
            ? $sessions->sum('school_invoice_amount')
            : $sessions->sum('therapist_billable_amount');

        return new BillingRunResultDTO(
            billingScheduleId: $schedule->id,
            status: BillingScheduleRunStatus::SUCCESS->value,
            billingPeriodStart: $periodStart->toDateString(),
            billingPeriodEnd: $periodEnd->toDateString(),
            sessionsFound: $sessions->count(),
            sessionsFromPriorPeriods: $sessionsFromPrior,
            adjustmentsCount: 0,
            adjustmentTotal: 0,
            carryForwardAmount: 0,
            invoiceId: null,
            therapistBillId: null,
            totalAmount: round((float) $total, 2),
            autoSent: false,
        );
    }

    private function buildFailedResult(BillingSchedule $schedule, string $errorMessage): BillingRunResultDTO
    {
        $period = $this->resolveCurrentPeriod($schedule);

        return new BillingRunResultDTO(
            billingScheduleId: $schedule->id,
            status: BillingScheduleRunStatus::FAILED->value,
            billingPeriodStart: $period['start']->toDateString(),
            billingPeriodEnd: $period['end']->toDateString(),
            sessionsFound: 0,
            sessionsFromPriorPeriods: 0,
            adjustmentsCount: 0,
            adjustmentTotal: 0,
            carryForwardAmount: 0,
            invoiceId: null,
            therapistBillId: null,
            totalAmount: null,
            autoSent: false,
            errorMessage: $errorMessage,
        );
    }
}
