<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Time\UserTimezoneService;
use App\Enums\SessionLogStatus;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\SessionLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only computed view that merges per-session charges (from approved
 * session_logs) with payment / credit-note / refund ledger entries for a
 * given school. INVOICE_GENERATED ledger rows are intentionally excluded —
 * their per-lesson detail comes from session_logs instead, and including both
 * would double-count the charge side.
 *
 * The running balance computed here will not equal LedgerEntry.balance_after
 * on the canonical ledger because the debit-side rows are different. This is
 * documented on the page footnote.
 *
 * Window strategy: rows are loaded for a bounded date range (default 30 days).
 * The opening balance — the running total of every signed_amount before the
 * window — is fetched as two cheap SUM queries, then the in-window rows walk
 * forward from that opening balance. The numbers reconcile with a full-history
 * walk because addition is associative.
 */
class SchoolAccountViewService
{
    public const DEFAULT_WINDOW_DAYS = 30;

    public function __construct(
        private readonly UserTimezoneService $timezones,
    ) {}

    /**
     * Build the merged transaction list for a school's account view, bounded
     * by an inclusive date range expressed in the school's local timezone.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getTransactions(
        School $school,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        [$fromUtc, $toUtc] = $this->resolveWindowUtc($school, $from, $to);

        $openingBalance = $this->openingBalance($school, $fromUtc);

        $charges = $this->loadCharges($school, $fromUtc, $toUtc);
        $adjustments = $this->loadAdjustments($school, $fromUtc, $toUtc);

        /** @var Collection<int, array<string, mixed>> $merged */
        $merged = $charges->concat($adjustments)->values();

        $sortedAsc = $merged
            ->sortBy([
                ['sort_key', 'asc'],
                ['source_type', 'asc'],
                ['source_id', 'asc'],
            ])
            ->values();

        $balance = $openingBalance;
        $withBalance = $sortedAsc->map(function (array $row) use (&$balance): array {
            $balance += (float) $row['signed_amount'];
            $row['balance_after'] = $balance;

            return $row;
        });

        /** @var Collection<int, array<string, mixed>> $result */
        $result = $withBalance
            ->sortBy([
                ['sort_key', 'desc'],
                ['source_type', 'desc'],
                ['source_id', 'desc'],
            ])
            ->values();

        return $result;
    }

    /**
     * Default 30-day window ending today, computed in the school's timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultWindow(School $school): array
    {
        $tz = $school->timezone ?: 'UTC';
        $today = CarbonImmutable::today($tz);
        $from = $today->subDays(self::DEFAULT_WINDOW_DAYS - 1);

        return [$from, $today];
    }

    /**
     * All-time totals for the summary strip. Independent of getTransactions()
     * so rendering the summary never forces the windowed merge.
     *
     * @return array{
     *     total_invoiced: float,
     *     total_paid: float,
     *     total_charges: float,
     *     total_credit_notes: float,
     *     total_refunds: float,
     *     net_balance: float,
     * }
     */
    public function getSummary(School $school): array
    {
        $totalCharges = (float) $this->baseChargeQuery($school)->sum('school_invoice_amount');

        $ledgerTotals = LedgerEntry::query()
            ->forAccount(School::class, $school->id)
            ->whereIn('transaction_type', [
                TransactionType::INVOICE_GENERATED->value,
                TransactionType::PAYMENT_RECEIVED->value,
                TransactionType::CREDIT_NOTE->value,
                TransactionType::REFUND->value,
            ])
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $totalInvoiced = (float) ($ledgerTotals[TransactionType::INVOICE_GENERATED->value]->total ?? 0.0);
        $totalPaid = (float) ($ledgerTotals[TransactionType::PAYMENT_RECEIVED->value]->total ?? 0.0);
        $totalCreditNotes = (float) ($ledgerTotals[TransactionType::CREDIT_NOTE->value]->total ?? 0.0);
        $totalRefunds = (float) ($ledgerTotals[TransactionType::REFUND->value]->total ?? 0.0);

        // Net balance still uses session-log charges (not invoiced), matching
        // the running balance in the transaction table. Sign convention lives
        // in TransactionType::balanceDelta() so the net never drifts from the
        // per-row signed_amount math.
        $netBalance = $totalCharges
            + ($totalPaid * TransactionType::PAYMENT_RECEIVED->balanceDelta())
            + ($totalCreditNotes * TransactionType::CREDIT_NOTE->balanceDelta())
            + ($totalRefunds * TransactionType::REFUND->balanceDelta());

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_charges' => $totalCharges,
            'total_credit_notes' => $totalCreditNotes,
            'total_refunds' => $totalRefunds,
            'net_balance' => $netBalance,
        ];
    }

    /**
     * Convert the user-supplied local date range into the UTC interval used
     * for SQL filtering. Uses the start-of-day for `from` and end-of-day for
     * `to`, both resolved in the school's timezone via UserTimezoneService.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveWindowUtc(
        School $school,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $tz = $school->timezone ?: null;

        [$fromUtc] = $this->timezones->userDayUtcRange($from, null, $tz);
        [, $toUtc] = $this->timezones->userDayUtcRange($to, null, $tz);

        return [
            CarbonImmutable::instance($fromUtc),
            CarbonImmutable::instance($toUtc),
        ];
    }

    /**
     * Sum signed_amount for every row dated strictly before $fromUtc. Captures
     * all-prior history in one number so the windowed walk reproduces the
     * correct running balance without loading historical rows.
     */
    private function openingBalance(School $school, CarbonImmutable $fromUtc): float
    {
        $chargesBefore = (float) $this->baseChargeQuery($school)
            ->where('start_time', '<', $fromUtc)
            ->sum('school_invoice_amount');

        $adjustmentsBefore = $this->baseAdjustmentQuery($school)
            ->where('recorded_at', '<', $fromUtc)
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $signedAdjustments = 0.0;
        foreach ([
            TransactionType::PAYMENT_RECEIVED,
            TransactionType::CREDIT_NOTE,
            TransactionType::REFUND,
        ] as $type) {
            $sum = (float) ($adjustmentsBefore[$type->value]->total ?? 0.0);
            $signedAdjustments += $sum * $type->balanceDelta();
        }

        return $chargesBefore + $signedAdjustments;
    }

    /**
     * Shared filter for every "charge" query (window rows, opening balance,
     * summary). Keeping it in one place guarantees the opening balance and
     * the windowed rows agree on what counts as a charge.
     *
     * @return Builder<SessionLog>
     */
    private function baseChargeQuery(School $school): Builder
    {
        return SessionLog::query()
            ->where('school_id', $school->id)
            ->withStatuses([SessionLogStatus::APPROVED])
            ->where('is_billable_school', true);
    }

    /**
     * Shared filter for every "adjustment" query.
     *
     * @return Builder<LedgerEntry>
     */
    private function baseAdjustmentQuery(School $school): Builder
    {
        return LedgerEntry::query()
            ->forAccount(School::class, $school->id)
            ->whereIn('transaction_type', [
                TransactionType::PAYMENT_RECEIVED->value,
                TransactionType::CREDIT_NOTE->value,
                TransactionType::REFUND->value,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadCharges(
        School $school,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): Collection {
        $tz = $school->timezone ?: 'UTC';

        return $this->baseChargeQuery($school)
            ->whereBetween('start_time', [$fromUtc, $toUtc])
            ->with(['student', 'therapist', 'service'])
            ->get()
            ->map(fn (SessionLog $log): array => SchoolAccountRowMapper::mapSessionLog($log, $tz));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadAdjustments(
        School $school,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): Collection {
        $tz = $school->timezone ?: 'UTC';

        return $this->baseAdjustmentQuery($school)
            ->whereBetween('recorded_at', [$fromUtc, $toUtc])
            ->with(['reference', 'recordedBy'])
            ->get()
            ->map(fn (LedgerEntry $entry): array => SchoolAccountRowMapper::mapLedgerEntry($entry, $tz));
    }
}
