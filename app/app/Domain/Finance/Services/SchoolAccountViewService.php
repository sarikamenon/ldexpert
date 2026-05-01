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
     *     total_charges: float,
     *     total_payments: float,
     *     total_credit_notes: float,
     *     total_refunds: float,
     *     net_balance: float,
     *     transaction_count: int,
     * }
     */
    public function getSummary(School $school): array
    {
        $totalCharges = (float) $this->baseChargeQuery($school)->sum('school_invoice_amount');
        $chargeCount = $this->baseChargeQuery($school)->count();

        $ledgerTotals = $this->baseAdjustmentQuery($school)
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $totalPayments = (float) ($ledgerTotals[TransactionType::PAYMENT_RECEIVED->value]->total ?? 0.0);
        $totalCreditNotes = (float) ($ledgerTotals[TransactionType::CREDIT_NOTE->value]->total ?? 0.0);
        $totalRefunds = (float) ($ledgerTotals[TransactionType::REFUND->value]->total ?? 0.0);
        $adjustmentCount = (int) $ledgerTotals->sum('count');

        // Sign convention lives in TransactionType::balanceDelta(); mirror it
        // here so the net never drifts from the per-row signed_amount math.
        $netBalance = $totalCharges
            + ($totalPayments * TransactionType::PAYMENT_RECEIVED->balanceDelta())
            + ($totalCreditNotes * TransactionType::CREDIT_NOTE->balanceDelta())
            + ($totalRefunds * TransactionType::REFUND->balanceDelta());

        return [
            'total_charges' => $totalCharges,
            'total_payments' => $totalPayments,
            'total_credit_notes' => $totalCreditNotes,
            'total_refunds' => $totalRefunds,
            'net_balance' => $netBalance,
            'transaction_count' => $chargeCount + $adjustmentCount,
        ];
    }

    /**
     * Convert the user-supplied local date range into the UTC half-open
     * interval used for SQL filtering. `to` is inclusive of the whole local
     * day, so we extend it to the end of the day in the school's timezone
     * before converting.
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
            ->where('status', SessionLogStatus::APPROVED->value)
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
            ->map(fn (SessionLog $log): array => $this->mapSessionLog($log, $tz));
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
            ->map(fn (LedgerEntry $entry): array => $this->mapLedgerEntry($entry, $tz));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSessionLog(SessionLog $log, string $tz): array
    {
        $amount = (float) $log->school_invoice_amount;

        // Display the calendar date and wall-clock time both derived from
        // start_time converted to the school's timezone, so they always agree.
        // Deriving the date from session_date instead would shift the whole
        // day when TZ-converted (a session at 6 AM NYC has session_date in UTC
        // = same day, but session_date midnight UTC → NYC = previous day).
        $startInSchoolTz = $log->start_time->copy()->setTimezone($tz);
        $startTime = $startInSchoolTz->format('g:i A');
        $duration = (int) $log->duration_minutes;

        return [
            'date' => CarbonImmutable::instance($startInSchoolTz),
            'sort_key' => $this->buildSessionSortKey($log),
            'type' => 'charge',
            'type_label' => 'Charge',
            'student_id' => $log->student_id,
            'student_name' => $log->student?->name,
            'service_name' => $log->service?->name,
            'therapist_name' => $log->therapist?->name,
            'session_time' => $startTime,
            'session_duration_minutes' => $duration,
            'schedule_id' => $log->schedule_id,
            'description_primary' => $this->buildSessionPrimaryLine($log),
            'description_secondary' => $startTime.' · '.$duration.' min',
            'debit' => $amount,
            'credit' => null,
            'reference' => null,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => null,
            'recorded_by' => null,
            'signed_amount' => $amount,
            'source_id' => 'session:'.$log->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLedgerEntry(LedgerEntry $entry, string $tz): array
    {
        $type = $entry->transaction_type;
        $amount = (float) $entry->amount;
        $isDebit = $type === TransactionType::REFUND;
        $notes = $entry->notes;

        // recorded_at is stored in UTC; convert to the school's timezone so the
        // displayed calendar date matches what users in the school's frame of
        // reference experienced.
        $recordedInSchoolTz = CarbonImmutable::instance($entry->recorded_at)->setTimezone($tz);

        return [
            'date' => $recordedInSchoolTz,
            // Sort key uses the UTC instant so charges and adjustments share a
            // common timeline. Display TZ doesn't affect ordering.
            'sort_key' => $entry->recorded_at->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'type' => $type->value,
            'type_label' => $type->label(),
            'student_id' => null,
            'student_name' => null,
            'service_name' => null,
            'therapist_name' => null,
            'session_time' => null,
            'session_duration_minutes' => null,
            'schedule_id' => null,
            'description_primary' => $notes !== null && $notes !== '' ? $notes : $type->label(),
            'description_secondary' => null,
            'debit' => $isDebit ? $amount : null,
            'credit' => $isDebit ? null : $amount,
            'reference' => $entry->reference,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'notes' => $notes,
            'recorded_by' => $entry->recordedBy?->name,
            'signed_amount' => $amount * $type->balanceDelta(),
            'source_id' => 'ledger:'.$entry->id,
        ];
    }

    private function buildSessionSortKey(SessionLog $log): string
    {
        // Sort key uses UTC start_time so charges and adjustments share a
        // common timeline regardless of which TZ each is displayed in.
        return $log->start_time->format('Y-m-d H:i:s');
    }

    private function buildSessionPrimaryLine(SessionLog $log): string
    {
        $service = $log->service?->name;
        $therapist = $log->therapist?->name;

        if ($service !== null && $service !== '' && $therapist !== null) {
            return $service.' · '.$therapist;
        }
        if ($service !== null && $service !== '') {
            return $service;
        }
        if ($therapist !== null) {
            return $therapist;
        }

        return 'Session';
    }
}
