<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Enums\BillingMode;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
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
    ) {}

    /**
     * Reconcile the prior calendar month for a single advance schedule.
     *
     * @return array{schedule_id: int, status: string, period_start: string, period_end: string, net_amount: float, settlement_invoice_id: ?int, credit_note_ledger_entry_id: ?int, lines: int}
     */
    public function reconcileSchedule(BillingSchedule $schedule, Carbon $referenceDate, bool $dryRun = false): array
    {
        $periodStart = $referenceDate->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $periodEnd = $referenceDate->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();

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

        // Per-session late delta, strictly within the prior calendar month.
        $deltas = $this->computeLateDeltas($schoolId, $periodStart, $periodEnd);

        if ($dryRun) {
            $net = $deltas->sum(fn (array $d): float => $d['delta']);

            return [...$base, 'status' => 'dry_run', 'net_amount' => round((float) $net, 2), 'lines' => $deltas->count()];
        }

        return DB::transaction(function () use ($schedule, $schoolId, $periodStart, $periodEnd, $deltas, $base): array {
            $charges = $deltas->filter(fn (array $d): bool => $d['delta'] > 0)->values();
            $credits = $deltas->filter(fn (array $d): bool => $d['delta'] < 0)->values();

            $settlementInvoiceId = null;
            if ($charges->isNotEmpty()) {
                $settlementInvoiceId = $this->createSettlementInvoice($schedule, $schoolId, $periodStart, $periodEnd, $charges);
            }

            $creditLedgerEntryId = null;
            $creditTotal = (float) $credits->sum(fn (array $d): float => abs($d['delta']));
            if ($creditTotal >= 0.01) {
                $creditLedgerEntryId = $this->createReconciliationCreditNote($schedule, $schoolId, $periodStart, $creditTotal);
            }

            $net = (float) $deltas->sum(fn (array $d): float => $d['delta']);

            AdvanceReconciliation::query()->create([
                'billing_schedule_id' => $schedule->id,
                'school_id' => $schoolId,
                'reconciled_period_start' => $periodStart->toDateString(),
                'reconciled_period_end' => $periodEnd->toDateString(),
                'source_invoice_id' => null,
                'credit_note_ledger_entry_id' => $creditLedgerEntryId,
                'settlement_invoice_id' => $settlementInvoiceId,
                'net_amount' => round($net, 2),
                'reconciled_at' => now(),
                'recorded_by_id' => null,
            ]);

            return [
                ...$base,
                'status' => 'reconciled',
                'net_amount' => round($net, 2),
                'settlement_invoice_id' => $settlementInvoiceId,
                'credit_note_ledger_entry_id' => $creditLedgerEntryId,
                'lines' => $deltas->count(),
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
     * Per-session late delta = should_bill − already_billed, for prior-month logs.
     *
     * @return Collection<int, array{session: SessionLog, delta: float}>
     */
    private function computeLateDeltas(int $schoolId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        /** @var Collection<int, SessionLog> $logs */
        $logs = SessionLog::query()
            ->where('school_id', $schoolId)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->whereBetween('session_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->with(['service', 'student', 'schedule'])
            ->get();

        $billed = $this->billedTotalsForPeriod($schoolId, $periodStart, $periodEnd);

        return $logs
            ->map(function (SessionLog $log) use ($billed): array {
                $shouldBill = $log->is_billable_school ? (float) $log->school_invoice_amount : 0.0;

                $key = $log->schedule_id !== null
                    ? 'schedule:'.$log->schedule_id
                    : 'session:'.$log->id;
                $alreadyBilled = $billed[$key] ?? 0.0;

                return ['session' => $log, 'delta' => round($shouldBill - $alreadyBilled, 2)];
            })
            ->filter(fn (array $d): bool => abs($d['delta']) >= 0.01)
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
     * Create a DRAFT settlement invoice for net-positive catch-up charges.
     *
     * @param  Collection<int, array{session: SessionLog, delta: float}>  $charges
     */
    private function createSettlementInvoice(
        BillingSchedule $schedule,
        int $schoolId,
        Carbon $periodStart,
        Carbon $periodEnd,
        Collection $charges,
    ): int {
        $school = $this->schoolRepository->find($schoolId);
        $schoolSnapshot = $school !== null ? $this->invoiceService->copySchoolSnapshot($school) : [];
        $companySnapshot = $this->invoiceService->copyCompanySnapshot();

        $subtotal = round((float) $charges->sum(fn (array $d): float => $d['delta']), 2);
        $paymentTermsDays = (int) $schedule->payment_terms_days;

        $invoice = $this->invoiceRepository->create([
            'school_id' => $schoolId,
            'invoice_number' => $this->invoiceRepository->generateInvoiceNumber(),
            'invoice_date' => now()->toDateString(),
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'billing_mode' => BillingMode::ADVANCE->value,
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => $subtotal,
            'tax_total' => 0,
            'total' => $subtotal,
            'due_date' => now()->addDays($paymentTermsDays)->toDateString(),
            'notes' => 'Late-approval catch-up for '.$periodStart->format('M Y').'.',
            ...$schoolSnapshot,
            ...$companySnapshot,
        ]);

        $sortOrder = 0;
        $lineItems = $charges->map(function (array $d) use ($periodStart, $periodEnd, &$sortOrder): array {
            $session = $d['session'];
            $serviceName = $session->service->name ?? 'Session';
            $date = $session->session_date->format('D M j');

            return [
                'line_type' => InvoiceLineType::ADJUST_EXTRA_SESSION->value,
                'description' => "{$serviceName} — {$date} (late approval)",
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'quantity' => 1,
                'unit_price' => $d['delta'],
                'total' => $d['delta'],
                'sort_order' => $sortOrder++,
                'schedule_id' => $session->schedule_id,
                'session_log_id' => $session->id,
            ];
        })->all();

        $invoice->lineItems()->createMany($lineItems);

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
            ->where('role', 'admin')
            ->orderBy('id')
            ->firstOrFail();

        return (int) $user->id;
    }
}
