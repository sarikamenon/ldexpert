<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\SessionLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only computed view that merges per-session charges (from session_logs
 * whose therapist bill has been paid) with payment / credit-note / refund
 * ledger entries for a given school. INVOICE_GENERATED ledger rows are
 * intentionally excluded — their per-lesson detail comes from session_logs
 * instead, and including both would double-count the charge side.
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
     * Each row is an associative array with the keys consumed by
     * SchoolAccountRowTransformer.
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
        $result = $withBalance->sortByDesc('sort_key')->values();

        return $result;
    }

    /**
     * Current balance under the per-session view (debits - credits using
     * TransactionType::balanceDelta()). Useful for the balance card.
     */
    public function getCurrentBalance(School $school): float
    {
        $rows = $this->getTransactions($school);
        if ($rows->isEmpty()) {
            return 0.0;
        }

        return (float) ($rows->first()['balance_after'] ?? 0.0);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadCharges(School $school): Collection
    {
        return SessionLog::query()
            ->where('school_id', $school->id)
            ->whereHas('therapistBill', function ($query): void {
                $query->where('status', TherapistBillStatus::PAID->value); // @phpstan-ignore argument.type
            })
            ->with(['student', 'therapist', 'service', 'therapistBill'])
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
        $sortKey = $this->buildSessionSortKey($log);

        $description = $this->buildSessionDescription($log);

        return [
            'date' => CarbonImmutable::parse($log->session_date->format('Y-m-d')),
            'sort_key' => $sortKey,
            'type' => 'charge',
            'student_name' => $log->student?->name,
            'description' => $description,
            'debit' => $amount,
            'credit' => null,
            'reference_html' => null,
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

        return [
            'date' => CarbonImmutable::parse($entry->recorded_at->format('Y-m-d H:i:s')),
            'sort_key' => $entry->recorded_at->format('Y-m-d H:i:s'),
            'type' => $type->value,
            'type_label' => $type->label(),
            'student_name' => null,
            'description' => $entry->notes !== null && $entry->notes !== ''
                ? $entry->notes
                : $type->label(),
            'debit' => $isDebit ? $amount : null,
            'credit' => $isDebit ? null : $amount,
            'reference' => $entry->reference,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'notes' => $entry->notes,
            'recorded_by' => $entry->recordedBy?->name,
            'signed_amount' => $amount * $type->balanceDelta(),
            'source_id' => 'ledger:'.$entry->id,
        ];
    }

    private function buildSessionSortKey(SessionLog $log): string
    {
        // session_date is a date column (no time-of-day). Pin time-of-day to
        // the session's start_time when available so multiple lessons on the
        // same day order chronologically; fall back to 00:00:00.
        $date = $log->session_date->format('Y-m-d');
        $time = $log->start_time->format('H:i:s');

        return $date.' '.$time;
    }

    private function buildSessionDescription(SessionLog $log): string
    {
        $startTime = $log->start_time->format('g:i A');
        $duration = (int) $log->duration_minutes;
        $service = $log->service?->name;
        $therapist = $log->therapist?->name;

        $parts = [];
        $parts[] = $startTime.' ('.$duration.' min.)';
        if ($service !== null && $service !== '') {
            $parts[] = $service;
        }
        if ($therapist !== null) {
            $parts[] = 'with '.$therapist;
        }

        return implode(' - ', $parts);
    }
}
