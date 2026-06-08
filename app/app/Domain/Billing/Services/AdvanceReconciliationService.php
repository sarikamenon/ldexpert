<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\InvoiceLineItemDTO;
use App\Enums\BillingMode;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Enums\SessionLogStatus;
use App\Models\AdvanceReconciliation;
use App\Models\BillingSchedule;
use App\Models\InvoiceLineItem;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 10th-of-month catch-up reconciliation for advance schedules (§8).
 *
 * Re-reconciles the immediately prior calendar month to catch session logs
 * approved AFTER the 1st-of-month automated run. Strictly prior-month only —
 * current-month logs are still in their open advance period and must never be
 * touched here. Idempotent via the advance_reconciliations table.
 */
final class AdvanceReconciliationService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoiceService $invoiceService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly LedgerService $ledgerService,
        private readonly AdvanceAdjustmentClassifier $adjustmentClassifier,
        private readonly BillingScheduleService $billingScheduleService,
    ) {}

    /**
     * Reconcile the prior calendar month for a single advance schedule.
     *
     * The schedule's frequency may split the month into several billing periods
     * (e.g. semi-monthly → 1st–15th and 16th–EOM). Each sub-period is reconciled
     * independently against the exact boundaries the 1st-of-month run stamped on
     * the original invoice lines, so already-billed totals match and no session is
     * re-charged. Returns one result row per reconciled sub-period.
     *
     * @return array<int, array{schedule_id: int, status: string, period_start: string, period_end: string, net_amount: float, settlement_invoice_id: ?int, credit_note_ledger_entry_id: ?int, lines: int}>
     */
    public function reconcileSchedule(BillingSchedule $schedule, Carbon $referenceDate, bool $dryRun = false): array
    {
        return $this->priorMonthPeriods($schedule, $referenceDate)
            ->map(fn (array $period): array => $this->reconcilePeriod($schedule, $period['start'], $period['end'], $dryRun))
            ->all();
    }

    /**
     * Enumerate the schedule's billing periods that fall within the prior calendar
     * month, derived from its own frequency (not a hardcoded full month).
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function priorMonthPeriods(BillingSchedule $schedule, Carbon $referenceDate): Collection
    {
        $monthStart = $referenceDate->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $monthEnd = $referenceDate->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();

        /** @var Collection<int, array{start: Carbon, end: Carbon}> $periods */
        $periods = new Collection;
        $cursor = $monthStart->copy();

        // Walk frequency periods from the 1st of the prior month; keep those whose
        // period END lands inside the prior month, so weekly/bi-weekly periods that
        // straddle a month boundary are reconciled with the month they close in.
        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $period = $this->billingScheduleService->determineBillingPeriod($schedule->frequency, $cursor);

            if ($period['end']->betweenIncluded($monthStart, $monthEnd)) {
                $periods->push([
                    'start' => $period['start']->copy()->startOfDay(),
                    'end' => $period['end']->copy()->startOfDay(),
                ]);
            }

            $cursor = $period['end']->copy()->addDay()->startOfDay();
        }

        return $periods;
    }

    /**
     * Reconcile a single billing period for a schedule.
     *
     * @return array{schedule_id: int, status: string, period_start: string, period_end: string, net_amount: float, settlement_invoice_id: ?int, credit_note_ledger_entry_id: ?int, lines: int}
     */
    private function reconcilePeriod(BillingSchedule $schedule, Carbon $periodStart, Carbon $periodEnd, bool $dryRun): array
    {
        $schoolId = (int) $schedule->schedulable_id;

        $base = [
            'schedule_id' => (int) $schedule->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'net_amount' => 0.0,
            'settlement_invoice_id' => null,
            'credit_note_ledger_entry_id' => null,
            'lines' => 0,
        ];

        // Idempotency guard: a period is reconciled at most once per schedule.
        if ($this->alreadyReconciled($schedule->id, $periodStart, $periodEnd)) {
            return [...$base, 'status' => 'skipped_already_reconciled'];
        }

        // Per-session late delta, strictly within the prior calendar month, classified
        // into status-based ADJUST_* lines (no-show / cancelled / rate-diff / extra).
        $lines = $this->buildReconcileLines($schoolId, $periodStart, $periodEnd);
        $net = round((float) $lines->sum(fn (InvoiceLineItemDTO $l): float => $l->total), 2);

        if ($dryRun) {
            return [...$base, 'status' => 'dry_run', 'net_amount' => $net, 'lines' => $lines->count()];
        }

        return DB::transaction(function () use ($schedule, $schoolId, $periodStart, $periodEnd, $lines, $net, $base): array {
            // Net decides ONE document (never both): owed-to-us → settlement invoice
            // carrying every status-based line; owed-to-family → a single credit note.
            $settlementInvoiceId = null;
            $creditLedgerEntryId = null;

            if ($net >= 0.01) {
                $settlementInvoiceId = $this->createSettlementInvoice($schedule, $schoolId, $periodStart, $periodEnd, $lines, $net);
            } elseif ($net <= -0.01) {
                $creditLedgerEntryId = $this->createReconciliationCreditNote($schedule, $schoolId, $periodStart, abs($net));
            }

            AdvanceReconciliation::query()->create([
                'billing_schedule_id' => $schedule->id,
                'school_id' => $schoolId,
                'reconciled_period_start' => $periodStart->toDateString(),
                'reconciled_period_end' => $periodEnd->toDateString(),
                'source_invoice_id' => null,
                'credit_note_ledger_entry_id' => $creditLedgerEntryId,
                'settlement_invoice_id' => $settlementInvoiceId,
                'net_amount' => $net,
                'reconciled_at' => now(),
                'recorded_by_id' => null,
            ]);

            return [
                ...$base,
                'status' => 'reconciled',
                'net_amount' => $net,
                'settlement_invoice_id' => $settlementInvoiceId,
                'credit_note_ledger_entry_id' => $creditLedgerEntryId,
                'lines' => $lines->count(),
            ];
        });
    }

    private function alreadyReconciled(int $scheduleId, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return AdvanceReconciliation::query()
            ->where('billing_schedule_id', $scheduleId)
            ->where('reconciled_period_start', $periodStart->toDateString())
            ->where('reconciled_period_end', $periodEnd->toDateString())
            ->exists();
    }

    /**
     * Build status-based reconciliation lines for prior-month sessions.
     *
     * Line AMOUNT = should_bill − already_billed (the late catch-up delta, so an
     * already-reconciled session nets to 0 and is dropped — never re-billed). Line
     * TYPE/label = the session outcome via the shared classifier, so the settlement
     * invoice reads like the regular advance invoice (no-show / cancelled / rate
     * adjustment / additional session).
     *
     * @return Collection<int, InvoiceLineItemDTO>
     */
    private function buildReconcileLines(int $schoolId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        /** @var Collection<int, SessionLog> $logs */
        $logs = SessionLog::query()
            ->forSchoolId($schoolId)
            ->withStatuses([SessionLogStatus::APPROVED->value])
            ->betweenSessionDates($periodStart->toDateString(), $periodEnd->toDateString())
            ->with(['service', 'student', 'schedule'])
            ->get();

        $billed = $this->billedTotalsForPeriod($schoolId, $periodStart, $periodEnd);

        $sortOrder = 0;

        return $logs
            ->map(function (SessionLog $log) use ($billed, $periodStart, $periodEnd, &$sortOrder): ?InvoiceLineItemDTO {
                $shouldBill = $log->is_billable_school ? (float) $log->school_invoice_amount : 0.0;

                $key = $log->schedule_id !== null
                    ? 'schedule:'.$log->schedule_id
                    : 'session:'.$log->id;
                $alreadyBilled = $billed[$key] ?? 0.0;

                $delta = round($shouldBill - $alreadyBilled, 2);

                if (abs($delta) < 0.01) {
                    return null;
                }

                $serviceName = $log->service->name ?? 'Session';
                $date = $log->session_date->format('D M j');

                // Never previously billed + a positive billable amount = a brand-new
                // (extra/unscheduled) session, mirroring the advance flow's extra-session line.
                if ($alreadyBilled <= 0.0 && $delta > 0) {
                    return new InvoiceLineItemDTO(
                        lineType: InvoiceLineType::ADJUST_EXTRA_SESSION->value,
                        description: "{$serviceName} — {$date} (additional session)",
                        billingPeriodStart: $periodStart->toDateString(),
                        billingPeriodEnd: $periodEnd->toDateString(),
                        quantity: 1,
                        unitPrice: $delta,
                        total: $delta,
                        sortOrder: $sortOrder++,
                        scheduleId: $log->schedule_id,
                        sessionLogId: $log->id,
                    );
                }

                $suffix = $this->adjustmentClassifier->descriptionSuffixFor($log->outcome);

                return new InvoiceLineItemDTO(
                    lineType: $this->adjustmentClassifier->lineTypeFor($log->outcome),
                    description: "{$serviceName} — {$date} — {$suffix}",
                    billingPeriodStart: $periodStart->toDateString(),
                    billingPeriodEnd: $periodEnd->toDateString(),
                    quantity: 1,
                    unitPrice: $delta,
                    total: $delta,
                    sortOrder: $sortOrder++,
                    scheduleId: $log->schedule_id,
                    sessionLogId: $log->id,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * Σ of each session's prior-month invoice_line_items.total across the school's
     * invoices (the original ADVANCE_SCHEDULED charge plus any ADJUST_* lines),
     * fetched in two grouped queries to avoid an N+1 over the session logs.
     *
     * Keyed by "schedule:{id}" when the line has a schedule, else "session:{id}".
     *
     * @return array<string, float>
     */
    private function billedTotalsForPeriod(int $schoolId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $base = fn (): \Illuminate\Database\Eloquent\Builder => InvoiceLineItem::query()
            ->whereHas('invoice', fn ($q) => $q->where('school_id', $schoolId)) // @phpstan-ignore argument.type
            ->where('billing_period_start', $periodStart->toDateString())
            ->where('billing_period_end', $periodEnd->toDateString());

        $byScheduleId = $base()
            ->whereNotNull('schedule_id')
            ->groupBy('schedule_id')
            ->selectRaw('schedule_id, SUM(total) as total')
            ->pluck('total', 'schedule_id');

        // Mirrors the original per-session lookup, which matched session_log_id
        // alone (no schedule_id filter) for logs that have no schedule.
        $bySessionLogId = $base()
            ->whereNotNull('session_log_id')
            ->groupBy('session_log_id')
            ->selectRaw('session_log_id, SUM(total) as total')
            ->pluck('total', 'session_log_id');

        $totals = [];
        foreach ($byScheduleId as $scheduleId => $total) {
            $totals['schedule:'.$scheduleId] = (float) $total;
        }
        foreach ($bySessionLogId as $sessionLogId => $total) {
            $totals['session:'.$sessionLogId] = (float) $total;
        }

        return $totals;
    }

    /**
     * Create a DRAFT settlement invoice for a net-positive reconciliation.
     *
     * Carries EVERY status-based reconciliation line (charges and credits alike,
     * like the regular advance invoice's "Adjustments from Previous Period"); the
     * invoice total is the net of them all.
     *
     * @param  Collection<int, InvoiceLineItemDTO>  $lines
     */
    private function createSettlementInvoice(
        BillingSchedule $schedule,
        int $schoolId,
        Carbon $periodStart,
        Carbon $periodEnd,
        Collection $lines,
        float $net,
    ): int {
        $school = $this->schoolRepository->find($schoolId);
        $schoolSnapshot = $school !== null ? $this->invoiceService->copySchoolSnapshot($school) : [];
        $companySnapshot = $this->invoiceService->copyCompanySnapshot();

        $paymentTermsDays = (int) $schedule->payment_terms_days;

        $invoice = $this->invoiceRepository->create([
            'school_id' => $schoolId,
            'invoice_number' => $this->invoiceRepository->generateInvoiceNumber(),
            'invoice_date' => now()->toDateString(),
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'billing_mode' => BillingMode::ADVANCE->value,
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => round($net, 2),
            'tax_total' => 0,
            'total' => round($net, 2),
            'due_date' => now()->addDays($paymentTermsDays)->toDateString(),
            'notes' => 'Late-approval catch-up for '.$periodStart->format('M Y').'.',
            ...$schoolSnapshot,
            ...$companySnapshot,
        ]);

        $invoice->lineItems()->createMany(
            $lines->map(fn (InvoiceLineItemDTO $l): array => $l->toArray())->all()
        );

        return (int) $invoice->id;
    }

    /**
     * Post an auto-credit note for net-negative late adjustments (we owe the family).
     * recorded_at = run date (the 10th), per Q9 — not backdated, no chain recompute.
     */
    private function createReconciliationCreditNote(
        BillingSchedule $schedule,
        int $schoolId,
        Carbon $periodStart,
        float $amount,
    ): int {
        $systemUserId = $this->systemUserId();

        $notes = sprintf(
            'Late-approval reconciliation credit for %s (schedule #%d).',
            $periodStart->format('M Y'),
            $schedule->id,
        );

        $entry = $this->ledgerService->createCreditNoteForSchool(
            $schoolId,
            round($amount, 2),
            $notes,
            $systemUserId,
            now(),
        );

        return (int) $entry->id;
    }

    private function systemUserId(): int
    {
        /** @var User $user */
        $user = User::query()
            ->byRole(Role::ADMIN)
            ->orderBy('id')
            ->firstOrFail();

        return (int) $user->id;
    }
}
