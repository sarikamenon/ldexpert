<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Enums\InvoiceStatus;
use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final class EloquentLedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, LedgerEntry>}
     */
    public function listForDataTables(string $ledgerableType, int $ledgerableId, DataTablesParamsDTO $params): array
    {
        $baseQuery = LedgerEntry::query()
            // @phpstan-ignore argument.type ($ledgerableType is a class-string fed in from controller-level whitelist)
            ->forAccount($ledgerableType, $ledgerableId)
            ->with(['recordedBy'])
            // Polymorphic eager-load: tell each concrete reference type which
            // relations it should preload so the row transformer doesn't
            // issue per-row queries (N+1 on Payment#X).
            ->with(['reference' => function (Relation $relation): void {
                if ($relation instanceof MorphTo) {
                    $relation->morphWith([
                        InvoicePayment::class => ['invoice'],
                        TherapistBillPayment::class => ['therapistBill'],
                    ]);
                }
            }]);

        $recordsTotal = (clone $baseQuery)->count();

        if ($params->searchValue) {
            $sv = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($sv): void {
                $q->where('notes', 'like', "%{$sv}%");
            });
        }
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumn = $params->orderColumn ?? 'ledger_entries.recorded_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir)
            ->orderBy('ledger_entries.id', $orderDir);

        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function getLastEntryForSchool(int $schoolId): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->forAccount(School::class, $schoolId)
            ->chainOrder('desc')
            ->first();
    }

    public function getLastEntryForTherapist(int $therapistId): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->forAccount(User::class, $therapistId)
            ->chainOrder('desc')
            ->first();
    }

    /**
     * @return array{
     *     total_invoiced: float,
     *     total_paid: float,
     *     outstanding: float,
     *     invoice_count: int,
     *     payment_count: int,
     *     total_credit_notes: float,
     *     credit_note_count: int,
     *     total_refunds: float,
     *     refund_count: int,
     *     current_balance: float,
     *     transaction_count: int
     * }
     */
    public function getSchoolStats(int $schoolId): array
    {
        // Calculate total invoiced from invoices table (source of truth)
        $totalInvoiced = (float) Invoice::where('school_id', $schoolId)
            ->whereIn('status', [InvoiceStatus::SENT->value, InvoiceStatus::PAID->value])
            ->sum('total');

        $invoiceCount = Invoice::where('school_id', $schoolId)
            ->whereIn('status', [InvoiceStatus::SENT->value, InvoiceStatus::PAID->value])
            ->count();

        $totals = $this->ledgerTotalsByType(School::class, $schoolId);
        $lastEntry = LedgerEntry::query()
            ->forAccount(School::class, $schoolId)
            ->chainOrder('desc')
            ->first();
        $currentBalance = $lastEntry !== null ? (float) $lastEntry->balance_after : 0.0;

        $totalPaid = $totals[TransactionType::PAYMENT_RECEIVED->value]['sum'];
        $totalCreditNotes = $totals[TransactionType::CREDIT_NOTE->value]['sum'];
        $totalRefunds = $totals[TransactionType::REFUND->value]['sum'];

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding' => $totalInvoiced - $totalPaid - $totalCreditNotes + $totalRefunds,
            'invoice_count' => $invoiceCount,
            'payment_count' => $totals[TransactionType::PAYMENT_RECEIVED->value]['count'],
            'total_credit_notes' => $totalCreditNotes,
            'credit_note_count' => $totals[TransactionType::CREDIT_NOTE->value]['count'],
            'total_refunds' => $totalRefunds,
            'refund_count' => $totals[TransactionType::REFUND->value]['count'],
            'current_balance' => $currentBalance,
            'transaction_count' => array_sum(array_column($totals, 'count')),
        ];
    }

    /**
     * @return array{
     *     total_billed: float,
     *     total_paid: float,
     *     outstanding: float,
     *     bill_count: int,
     *     payment_count: int,
     *     total_credit_notes: float,
     *     credit_note_count: int,
     *     total_refunds: float,
     *     refund_count: int,
     *     current_balance: float,
     *     transaction_count: int
     * }
     */
    public function getTherapistStats(int $therapistId): array
    {
        // Calculate total billed from therapist_bills table (source of truth)
        $totalBilled = (float) TherapistBill::where('therapist_id', $therapistId)
            ->whereIn('status', [TherapistBillStatus::SENT->value, TherapistBillStatus::PAID->value])
            ->sum('total_due');

        $billCount = TherapistBill::where('therapist_id', $therapistId)
            ->whereIn('status', [TherapistBillStatus::SENT->value, TherapistBillStatus::PAID->value])
            ->count();

        $totals = $this->ledgerTotalsByType(User::class, $therapistId);
        $lastEntry = LedgerEntry::query()
            ->forAccount(User::class, $therapistId)
            ->chainOrder('desc')
            ->first();
        $currentBalance = $lastEntry !== null ? (float) $lastEntry->balance_after : 0.0;

        $totalPaid = $totals[TransactionType::PAYMENT_MADE->value]['sum'];
        $totalCreditNotes = $totals[TransactionType::CREDIT_NOTE->value]['sum'];
        $totalRefunds = $totals[TransactionType::REFUND->value]['sum'];

        return [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'outstanding' => $totalBilled - $totalPaid - $totalCreditNotes + $totalRefunds,
            'bill_count' => $billCount,
            'payment_count' => $totals[TransactionType::PAYMENT_MADE->value]['count'],
            'total_credit_notes' => $totalCreditNotes,
            'credit_note_count' => $totals[TransactionType::CREDIT_NOTE->value]['count'],
            'total_refunds' => $totalRefunds,
            'refund_count' => $totals[TransactionType::REFUND->value]['count'],
            'current_balance' => $currentBalance,
            'transaction_count' => array_sum(array_column($totals, 'count')),
        ];
    }

    /**
     * Single grouped aggregate over the account ledger so we don't fire one
     * query per transaction-type bucket. Returns sums and counts keyed by the
     * TransactionType enum string value, with zero defaults for absent buckets.
     *
     * @param  class-string  $ledgerableType
     * @return array<string, array{sum: float, count: int}>
     */
    private function ledgerTotalsByType(string $ledgerableType, int $ledgerableId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, LedgerEntry&object{sum: string|null, count: int}> $rows */
        $rows = LedgerEntry::query()
            ->forAccount($ledgerableType, $ledgerableId)
            ->selectRaw('transaction_type, SUM(amount) as sum, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->get();

        $result = [];
        foreach (TransactionType::cases() as $case) {
            $result[$case->value] = ['sum' => 0.0, 'count' => 0];
        }
        foreach ($rows as $row) {
            $result[$row->transaction_type->value] = [
                'sum' => (float) ($row->sum ?? 0),
                'count' => (int) $row->count,
            ];
        }

        return $result;
    }
}
