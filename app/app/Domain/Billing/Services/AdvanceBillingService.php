<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Repositories\BillingScheduleRepositoryInterface;
use App\Domain\Billing\Repositories\InvoiceLineItemRepositoryInterface;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\DTOs\BillingRunResultDTO;
use App\DTOs\InvoiceLineItemDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleRunStatus;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AdvanceBillingService
{
    public function __construct(
        private readonly BillingScheduleRepositoryInterface $scheduleRepository,
        private readonly InvoiceLineItemRepositoryInterface $lineItemRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoiceService $invoiceService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly SessionLogRateService $rateService,
        private readonly BillingScheduleService $billingScheduleService,
    ) {}

    /**
     * Process an advance billing schedule for a private student.
     */
    public function processAdvanceSchedule(BillingSchedule $schedule, bool $dryRun = false): BillingRunResultDTO
    {
        if ($schedule->billing_mode !== BillingMode::ADVANCE) {
            throw new \InvalidArgumentException('Schedule is not in advance billing mode.');
        }

        $completedPeriod = $this->resolveCompletedPeriod($schedule);
        $upcomingPeriod = $this->resolveUpcomingPeriod($schedule);

        $studentId = $schedule->schedulable_id;

        $previousInvoice = $this->lineItemRepository->getPreviousAdvanceInvoice($studentId);

        $adjustmentLines = $this->buildAdjustmentLines(
            $previousInvoice,
            $completedPeriod['start'],
            $completedPeriod['end'],
        );

        $advanceLines = $this->buildAdvanceChargeLines(
            $studentId,
            $upcomingPeriod['start'],
            $upcomingPeriod['end'],
        );

        if ($adjustmentLines->isEmpty() && $advanceLines->isEmpty()) {
            $result = new BillingRunResultDTO(
                billingScheduleId: $schedule->id,
                status: BillingScheduleRunStatus::SKIPPED_NO_SESSIONS->value,
                billingPeriodStart: $upcomingPeriod['start']->toDateString(),
                billingPeriodEnd: $upcomingPeriod['end']->toDateString(),
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

            if (! $dryRun) {
                $this->logRun($schedule, $result);
                $this->billingScheduleService->advanceSchedule($schedule, $completedPeriod['end']);
            }

            return $result;
        }

        $carryForwardFromPrev = $previousInvoice !== null
            ? (float) $previousInvoice->carry_forward_balance
            : 0.0;

        if ($carryForwardFromPrev > 0 && $previousInvoice !== null) {
            $adjustmentLines->push(new InvoiceLineItemDTO(
                lineType: InvoiceLineType::CARRY_FORWARD_CREDIT->value,
                description: "Credit carried forward from Invoice #{$previousInvoice->invoice_number}",
                billingPeriodStart: $completedPeriod['start']->toDateString(),
                billingPeriodEnd: $completedPeriod['end']->toDateString(),
                quantity: 1,
                unitPrice: -$carryForwardFromPrev,
                total: -$carryForwardFromPrev,
                sortOrder: 0,
                sourceInvoiceId: $previousInvoice->id,
            ));
        }

        $adjustmentTotal = $adjustmentLines->sum(fn (InvoiceLineItemDTO $line): float => $line->total);
        $advanceTotal = $advanceLines->sum(fn (InvoiceLineItemDTO $line): float => $line->total);
        $netTotal = $advanceTotal + $adjustmentTotal;

        $newCarryForward = 0.0;
        if ($netTotal < 0) {
            $newCarryForward = abs($netTotal);
            $netTotal = 0.0;
        }

        if ($dryRun) {
            return new BillingRunResultDTO(
                billingScheduleId: $schedule->id,
                status: BillingScheduleRunStatus::SUCCESS->value,
                billingPeriodStart: $upcomingPeriod['start']->toDateString(),
                billingPeriodEnd: $upcomingPeriod['end']->toDateString(),
                sessionsFound: $advanceLines->count(),
                sessionsFromPriorPeriods: 0,
                adjustmentsCount: $adjustmentLines->count(),
                adjustmentTotal: round($adjustmentTotal, 2),
                carryForwardAmount: round($newCarryForward, 2),
                invoiceId: null,
                therapistBillId: null,
                totalAmount: round($netTotal, 2),
                autoSent: false,
            );
        }

        return DB::transaction(function () use (
            $schedule,
            $studentId,
            $completedPeriod,
            $upcomingPeriod,
            $adjustmentLines,
            $advanceLines,
            $adjustmentTotal,
            $advanceTotal,
            $netTotal,
            $newCarryForward,
        ): BillingRunResultDTO {
            $invoice = $this->createAdvanceInvoice(
                $studentId,
                $upcomingPeriod['start'],
                $upcomingPeriod['end'],
                $advanceTotal,
                $adjustmentTotal,
                $netTotal,
                $newCarryForward,
                $schedule->payment_terms_days,
            );

            $allLines = $this->mergeAndNumberLines($adjustmentLines, $advanceLines);
            $this->lineItemRepository->createMany(
                $invoice,
                $allLines->map(fn (InvoiceLineItemDTO $l): array => $l->toArray())->all()
            );

            $result = new BillingRunResultDTO(
                billingScheduleId: $schedule->id,
                status: BillingScheduleRunStatus::SUCCESS->value,
                billingPeriodStart: $upcomingPeriod['start']->toDateString(),
                billingPeriodEnd: $upcomingPeriod['end']->toDateString(),
                sessionsFound: $advanceLines->count(),
                sessionsFromPriorPeriods: 0,
                adjustmentsCount: $adjustmentLines->count(),
                adjustmentTotal: round($adjustmentTotal, 2),
                carryForwardAmount: round($newCarryForward, 2),
                invoiceId: $invoice->id,
                therapistBillId: null,
                totalAmount: round($netTotal, 2),
                autoSent: false,
            );

            $this->logRun($schedule, $result);
            $this->billingScheduleService->advanceSchedule($schedule, $completedPeriod['end']);

            return $result;
        });
    }

    /**
     * Generate a settlement invoice when a student leaves (adjustments only, no advance).
     */
    public function generateSettlementInvoice(BillingSchedule $schedule, Carbon $finalPeriodEnd): BillingRunResultDTO
    {
        $studentId = $schedule->schedulable_id;
        $previousInvoice = $this->lineItemRepository->getPreviousAdvanceInvoice($studentId);

        $completedPeriod = $this->resolveCompletedPeriod($schedule);

        $adjustmentLines = $this->buildAdjustmentLines(
            $previousInvoice,
            $completedPeriod['start'],
            $completedPeriod['end'],
        );

        $carryForwardFromPrev = $previousInvoice !== null
            ? (float) $previousInvoice->carry_forward_balance
            : 0.0;

        if ($carryForwardFromPrev > 0 && $previousInvoice !== null) {
            $adjustmentLines->push(new InvoiceLineItemDTO(
                lineType: InvoiceLineType::CARRY_FORWARD_CREDIT->value,
                description: "Credit carried forward from Invoice #{$previousInvoice->invoice_number}",
                billingPeriodStart: $completedPeriod['start']->toDateString(),
                billingPeriodEnd: $completedPeriod['end']->toDateString(),
                quantity: 1,
                unitPrice: -$carryForwardFromPrev,
                total: -$carryForwardFromPrev,
                sortOrder: 0,
                sourceInvoiceId: $previousInvoice->id,
            ));
        }

        $adjustmentTotal = $adjustmentLines->sum(fn (InvoiceLineItemDTO $line): float => $line->total);
        $netTotal = max(0.0, $adjustmentTotal);
        $newCarryForward = $adjustmentTotal < 0 ? abs($adjustmentTotal) : 0.0;

        return DB::transaction(function () use (
            $schedule,
            $studentId,
            $completedPeriod,
            $adjustmentLines,
            $adjustmentTotal,
            $netTotal,
            $newCarryForward,
        ): BillingRunResultDTO {
            $invoice = $this->createAdvanceInvoice(
                $studentId,
                $completedPeriod['start'],
                $completedPeriod['end'],
                0.0,
                $adjustmentTotal,
                $netTotal,
                $newCarryForward,
                (int) $schedule->payment_terms_days,
            );

            $numberedLines = $this->mergeAndNumberLines($adjustmentLines, collect());
            $this->lineItemRepository->createMany(
                $invoice,
                $numberedLines->map(fn (InvoiceLineItemDTO $l): array => $l->toArray())->all()
            );

            $result = new BillingRunResultDTO(
                billingScheduleId: $schedule->id,
                status: BillingScheduleRunStatus::SUCCESS->value,
                billingPeriodStart: $completedPeriod['start']->toDateString(),
                billingPeriodEnd: $completedPeriod['end']->toDateString(),
                sessionsFound: 0,
                sessionsFromPriorPeriods: 0,
                adjustmentsCount: $adjustmentLines->count(),
                adjustmentTotal: round($adjustmentTotal, 2),
                carryForwardAmount: round($newCarryForward, 2),
                invoiceId: $invoice->id,
                therapistBillId: null,
                totalAmount: round($netTotal, 2),
                autoSent: false,
            );

            $this->logRun($schedule, $result);

            return $result;
        });
    }

    /**
     * Build adjustment line items by comparing prior advance charges against actual session outcomes.
     *
     * @return Collection<int, InvoiceLineItemDTO>
     */
    public function buildAdjustmentLines(
        ?Invoice $previousInvoice,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        /** @var Collection<int, InvoiceLineItemDTO> $adjustments */
        $adjustments = collect();

        if ($previousInvoice === null) {
            return $adjustments;
        }

        $advanceLines = $this->lineItemRepository->getAdvanceLinesForPeriod(
            $previousInvoice->id,
            $periodStart,
            $periodEnd,
        );

        if ($advanceLines->isEmpty()) {
            return $adjustments;
        }

        $scheduleIds = $advanceLines->pluck('schedule_id')->filter()->all();

        /** @var Collection<int, SessionLog> $sessionLogs */
        $sessionLogs = SessionLog::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->get()
            ->keyBy('schedule_id');

        foreach ($advanceLines as $advanceLine) {
            $scheduleId = $advanceLine->schedule_id;

            if ($scheduleId === null) {
                continue;
            }

            $sessionLog = $sessionLogs->get($scheduleId);
            $advanceAmount = (float) $advanceLine->total;

            $adjustment = $this->calculateAdjustment(
                $advanceLine,
                $sessionLog,
                $advanceAmount,
                $periodStart,
                $periodEnd,
                $previousInvoice->id,
            );

            if ($adjustment !== null) {
                $adjustments->push($adjustment);
            }
        }

        $this->detectExtraSessions(
            $adjustments,
            $advanceLines,
            $previousInvoice,
            $periodStart,
            $periodEnd,
        );

        return $adjustments;
    }

    /**
     * Build advance charge line items from scheduled sessions for an upcoming period.
     *
     * @return Collection<int, InvoiceLineItemDTO>
     */
    public function buildAdvanceChargeLines(
        int $studentId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        /** @var Collection<int, InvoiceLineItemDTO> $lines */
        $lines = collect();

        /** @var Collection<int, Schedule> $schedules */
        $schedules = Schedule::query()
            ->where('student_id', $studentId)
            ->where('schedule_date', '>=', $periodStart->toDateString())
            ->where('schedule_date', '<=', $periodEnd->toDateString())
            ->where('status', ScheduleStatus::SCHEDULED->value)
            ->with(['service', 'therapist', 'school'])
            ->orderBy('schedule_date')
            ->get();

        foreach ($schedules as $schedule) {
            $rate = $this->getScheduleRate($schedule);

            if ($rate === null) {
                Log::warning('Could not determine rate for scheduled session', [
                    'schedule_id' => $schedule->id,
                    'student_id' => $studentId,
                ]);

                continue;
            }

            $serviceName = $schedule->service->name ?? 'Session';
            $date = $schedule->schedule_date->format('D M j');
            $duration = $schedule->durationMinutes();
            $isGroup = $schedule->is_group ? ' (group)' : '';

            $lines->push(new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADVANCE_SCHEDULED->value,
                description: "{$serviceName} — {$date} ({$duration} min){$isGroup}",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: $rate,
                total: $rate,
                scheduleId: $schedule->id,
            ));
        }

        return $lines;
    }

    /**
     * Calculate the adjustment for a single advance line item vs its actual outcome.
     */
    private function calculateAdjustment(
        InvoiceLineItem $advanceLine,
        ?SessionLog $sessionLog,
        float $advanceAmount,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $sourceInvoiceId,
    ): ?InvoiceLineItemDTO {
        if ($sessionLog === null) {
            return new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value,
                description: "{$advanceLine->description} — session did not occur (full credit)",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: -$advanceAmount,
                total: -$advanceAmount,
                scheduleId: $advanceLine->schedule_id,
                sourceInvoiceId: $sourceInvoiceId,
            );
        }

        $outcome = $sessionLog->outcome;

        if ($outcome === SessionOutcome::SERVICES_ADMINISTERED) {
            $actualAmount = (float) $sessionLog->school_invoice_amount;
            $difference = $actualAmount - $advanceAmount;

            if (abs($difference) < 0.01) {
                return null;
            }

            return new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADJUST_RATE_DIFFERENCE->value,
                description: "{$advanceLine->description} — rate adjustment",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: $difference,
                total: $difference,
                sessionLogId: $sessionLog->id,
                scheduleId: $advanceLine->schedule_id,
                sourceInvoiceId: $sourceInvoiceId,
            );
        }

        if ($outcome === SessionOutcome::NO_SHOW) {
            $noShowAmount = (float) $sessionLog->school_invoice_amount;
            $credit = $noShowAmount - $advanceAmount;

            if (abs($credit) < 0.01) {
                return null;
            }

            return new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADJUST_NO_SHOW->value,
                description: "{$advanceLine->description} — no-show (adjusted to no-show rate)",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: $credit,
                total: $credit,
                sessionLogId: $sessionLog->id,
                scheduleId: $advanceLine->schedule_id,
                sourceInvoiceId: $sourceInvoiceId,
            );
        }

        if ($outcome === SessionOutcome::BILLABLE_CANCELLATION) {
            $cancelAmount = (float) $sessionLog->school_invoice_amount;
            $credit = $cancelAmount - $advanceAmount;

            if (abs($credit) < 0.01) {
                return null;
            }

            return new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADJUST_CANCEL_BILLABLE->value,
                description: "{$advanceLine->description} — billable cancellation (adjusted)",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: $credit,
                total: $credit,
                sessionLogId: $sessionLog->id,
                scheduleId: $advanceLine->schedule_id,
                sourceInvoiceId: $sourceInvoiceId,
            );
        }

        if ($outcome === SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT
            || $outcome === SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER) {
            return new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value,
                description: "{$advanceLine->description} — cancelled ({$outcome->label()}, full credit)",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: -$advanceAmount,
                total: -$advanceAmount,
                sessionLogId: $sessionLog->id,
                scheduleId: $advanceLine->schedule_id,
                sourceInvoiceId: $sourceInvoiceId,
            );
        }

        return null;
    }

    /**
     * Detect sessions that occurred without a matching advance charge (extra/unscheduled sessions).
     *
     * @param  Collection<int, InvoiceLineItemDTO>  $adjustments
     * @param  Collection<int, InvoiceLineItem>  $advanceLines
     */
    private function detectExtraSessions(
        Collection $adjustments,
        Collection $advanceLines,
        Invoice $previousInvoice,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): void {
        $billedScheduleIds = $advanceLines->pluck('schedule_id')->filter()->all();

        $studentId = $previousInvoice->student_id;
        if ($studentId === null) {
            return;
        }

        /** @var Collection<int, SessionLog> $extraSessions */
        $extraSessions = SessionLog::query()
            ->where('student_id', $studentId)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->where('session_date', '>=', $periodStart->toDateString())
            ->where('session_date', '<=', $periodEnd->toDateString())
            ->where(function ($q) use ($billedScheduleIds): void {
                $q->whereNull('schedule_id')
                    ->orWhereNotIn('schedule_id', $billedScheduleIds);
            })
            ->whereNull('invoice_id')
            ->with(['service'])
            ->get();

        foreach ($extraSessions as $session) {
            $serviceName = $session->service->name ?? 'Session';
            $date = $session->session_date->format('D M j');

            $adjustments->push(new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADJUST_EXTRA_SESSION->value,
                description: "{$serviceName} — {$date} (additional session)",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: (float) $session->school_invoice_amount,
                total: (float) $session->school_invoice_amount,
                sessionLogId: $session->id,
                sourceInvoiceId: $previousInvoice->id,
            ));
        }
    }

    /**
     * Get the billing rate for a scheduled session using the rate service.
     */
    private function getScheduleRate(Schedule $schedule): ?float
    {
        try {
            $durationMinutes = $schedule->durationMinutes();
            $result = $this->rateService->calculateDualBilling(
                $schedule->therapist_id,
                $schedule->school_id,
                $schedule->service_id,
                $schedule->schedule_date->toDateString(),
                $durationMinutes,
            );

            return $result['school']['invoice_amount'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Rate calculation failed for schedule', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create the advance invoice record.
     */
    private function createAdvanceInvoice(
        int $studentId,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $advanceTotal,
        float $adjustmentTotal,
        float $netTotal,
        float $carryForward,
        int $paymentTermsDays,
    ): Invoice {
        /** @var User $student */
        $student = User::findOrFail($studentId);
        $studentProfile = StudentProfile::where('user_id', $studentId)->first();

        $schoolId = $studentProfile?->school_id;
        $parentId = $studentProfile?->parent_id;

        $school = $schoolId !== null ? $this->schoolRepository->find($schoolId) : null;
        /** @var User|null $parentUser */
        $parentUser = $parentId !== null ? User::find($parentId) : null;

        $schoolSnapshot = $school !== null
            ? $this->invoiceService->copySchoolSnapshot($school)
            : $this->emptySchoolSnapshot();

        $companySnapshot = $this->invoiceService->copyCompanySnapshot();
        $parentSnapshot = $this->copyParentSnapshot($parentUser);

        $invoiceNumber = $this->invoiceRepository->generateInvoiceNumber();

        $invoice = $this->invoiceRepository->create([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'parent_id' => $parentId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'billing_mode' => BillingMode::ADVANCE->value,
            'invoice_type' => 'private_student',
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => round($advanceTotal, 2),
            'tax_total' => 0,
            'total' => round($netTotal, 2),
            'carry_forward_balance' => round($carryForward, 2),
            'due_date' => now()->addDays($paymentTermsDays)->toDateString(),
            ...$schoolSnapshot,
            ...$companySnapshot,
            ...$parentSnapshot,
        ]);

        return $invoice;
    }

    /**
     * @return array<string, string|null>
     */
    private function copyParentSnapshot(?User $parent): array
    {
        if ($parent === null) {
            return [
                'parent_name' => null,
                'parent_email' => null,
                'parent_phone' => null,
                'parent_address' => null,
            ];
        }

        /** @var \App\Models\ParentProfile|null $parentProfile */
        $parentProfile = $parent->parentProfile;

        return [
            'parent_name' => $parent->name,
            'parent_email' => $parent->email,
            'parent_phone' => $parentProfile->phone ?? null,
            'parent_address' => $parentProfile->address ?? null,
        ];
    }

    /**
     * @return array<string, null>
     */
    private function emptySchoolSnapshot(): array
    {
        return [
            'school_name' => null,
            'school_display_name' => null,
            'school_address' => null,
            'school_state' => null,
            'school_contact_first_name' => null,
            'school_contact_last_name' => null,
            'school_contact_phone' => null,
            'school_contact_email' => null,
            'school_invoice_email' => null,
        ];
    }

    /**
     * Merge adjustment and advance line DTOs with correct sort ordering.
     *
     * @param  Collection<int, InvoiceLineItemDTO>  $adjustmentLines
     * @param  Collection<int, InvoiceLineItemDTO>  $advanceLines
     * @return Collection<int, InvoiceLineItemDTO>
     */
    private function mergeAndNumberLines(Collection $adjustmentLines, Collection $advanceLines): Collection
    {
        $sortOrder = 0;
        $result = collect();

        foreach ($adjustmentLines as $line) {
            $result->push(new InvoiceLineItemDTO(
                lineType: $line->lineType,
                description: $line->description,
                billingPeriodStart: $line->billingPeriodStart,
                billingPeriodEnd: $line->billingPeriodEnd,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                total: $line->total,
                sortOrder: $sortOrder++,
                scheduleId: $line->scheduleId,
                sessionLogId: $line->sessionLogId,
                sourceInvoiceId: $line->sourceInvoiceId,
            ));
        }

        foreach ($advanceLines as $line) {
            $result->push(new InvoiceLineItemDTO(
                lineType: $line->lineType,
                description: $line->description,
                billingPeriodStart: $line->billingPeriodStart,
                billingPeriodEnd: $line->billingPeriodEnd,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                total: $line->total,
                sortOrder: $sortOrder++,
                scheduleId: $line->scheduleId,
                sessionLogId: $line->sessionLogId,
                sourceInvoiceId: $line->sourceInvoiceId,
            ));
        }

        /** @var Collection<int, InvoiceLineItemDTO> $result */
        return $result;
    }

    /**
     * Resolve the completed period (for adjustments) based on schedule state.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveCompletedPeriod(BillingSchedule $schedule): array
    {
        if ($schedule->last_period_end !== null) {
            $nextDay = $schedule->last_period_end->copy()->addDay();

            return $this->billingScheduleService->determineBillingPeriod($schedule->frequency, $nextDay);
        }

        return $this->billingScheduleService->determineBillingPeriod($schedule->frequency, now());
    }

    /**
     * Resolve the upcoming period (for advance charges).
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveUpcomingPeriod(BillingSchedule $schedule): array
    {
        $completedPeriod = $this->resolveCompletedPeriod($schedule);
        $nextDay = $completedPeriod['end']->copy()->addDay();

        return $this->billingScheduleService->determineBillingPeriod($schedule->frequency, $nextDay);
    }

    private function logRun(BillingSchedule $schedule, BillingRunResultDTO $result): void
    {
        $this->scheduleRepository->logRun([
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
}
