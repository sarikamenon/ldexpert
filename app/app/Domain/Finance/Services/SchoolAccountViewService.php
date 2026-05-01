<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Enums\SessionLogStatus;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\SessionLog;
use Carbon\CarbonImmutable;
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
 */
class SchoolAccountViewService
{
    /**
     * Build the merged transaction list for a school's account view.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getTransactions(School $school): Collection
    {
        $charges = $this->loadCharges($school);
        $adjustments = $this->loadAdjustments($school);

        /** @var Collection<int, array<string, mixed>> $merged */
        $merged = $charges->concat($adjustments)->values();

        $sortedAsc = $merged
            ->sortBy([
                ['sort_key', 'asc'],
                ['source_id', 'asc'],
            ])
            ->values();

        $balance = 0.0;
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

    public function getCurrentBalance(School $school): float
    {
        // Sum signed amounts directly — independent of row order, so DESC
        // tie-breakers on equal sort_keys can't desync the displayed balance
        // from the true running total.
        $charges = $this->loadCharges($school)->sum('signed_amount');
        $adjustments = $this->loadAdjustments($school)->sum('signed_amount');

        return (float) ($charges + $adjustments);
    }

    /**
     * All-time totals for the summary strip. Computed from the same merged
     * collection so the numbers always reconcile with what the table shows.
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
        $rows = $this->getTransactions($school);

        $totalCharges = 0.0;
        $totalPayments = 0.0;
        $totalCreditNotes = 0.0;
        $totalRefunds = 0.0;
        $netBalance = 0.0;

        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            $amount = (float) ($row['debit'] ?? $row['credit'] ?? 0.0);
            $netBalance += (float) ($row['signed_amount'] ?? 0.0);

            match ($type) {
                'charge' => $totalCharges += $amount,
                TransactionType::PAYMENT_RECEIVED->value => $totalPayments += $amount,
                TransactionType::CREDIT_NOTE->value => $totalCreditNotes += $amount,
                TransactionType::REFUND->value => $totalRefunds += $amount,
                default => null,
            };
        }

        return [
            'total_charges' => $totalCharges,
            'total_payments' => $totalPayments,
            'total_credit_notes' => $totalCreditNotes,
            'total_refunds' => $totalRefunds,
            'net_balance' => $netBalance,
            'transaction_count' => $rows->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadCharges(School $school): Collection
    {
        return SessionLog::query()
            ->where('school_id', $school->id)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->with(['student', 'therapist', 'service'])
            ->get()
            ->map(fn (SessionLog $log): array => $this->mapSessionLog($log));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadAdjustments(School $school): Collection
    {
        $types = [
            TransactionType::PAYMENT_RECEIVED->value,
            TransactionType::CREDIT_NOTE->value,
            TransactionType::REFUND->value,
        ];

        return LedgerEntry::query()
            ->forAccount(School::class, $school->id)
            ->whereIn('transaction_type', $types)
            ->with(['reference', 'recordedBy'])
            ->get()
            ->map(fn (LedgerEntry $entry): array => $this->mapLedgerEntry($entry));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSessionLog(SessionLog $log): array
    {
        $amount = (float) $log->school_invoice_amount;

        // TODO(timezone): start_time is stored in UTC and is currently formatted
        // as UTC for the description's secondary line. Once the school-timezone
        // display rule is settled, convert via UserTimezoneService::toUserTimezone()
        // using the school's timezone. See CLAUDE.md "whose timezone for display".
        $startTime = $log->start_time->format('g:i A');
        $duration = (int) $log->duration_minutes;

        return [
            'date' => CarbonImmutable::parse($log->session_date->format('Y-m-d')),
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
    private function mapLedgerEntry(LedgerEntry $entry): array
    {
        $type = $entry->transaction_type;
        $amount = (float) $entry->amount;
        $isDebit = $type === TransactionType::REFUND;
        $notes = $entry->notes;

        return [
            'date' => CarbonImmutable::parse($entry->recorded_at->format('Y-m-d H:i:s')),
            'sort_key' => $entry->recorded_at->format('Y-m-d H:i:s'),
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
        $date = $log->session_date->format('Y-m-d');
        $time = $log->start_time->format('H:i:s');

        return $date.' '.$time;
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
