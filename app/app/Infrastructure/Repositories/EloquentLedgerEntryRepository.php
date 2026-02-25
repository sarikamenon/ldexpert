<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;

final class EloquentLedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, LedgerEntry>}
     */
    public function listForDataTables(string $ledgerableType, int $ledgerableId, DataTablesParamsDTO $params): array
    {
        $baseQuery = LedgerEntry::query()
            ->where('ledgerable_type', $ledgerableType)
            ->where('ledgerable_id', $ledgerableId)
            ->with(['reference', 'recordedBy']);

        $recordsTotal = (clone $baseQuery)->count();

        if ($params->searchValue) {
            $sv = $params->searchValue;
            $baseQuery->where(function ($q) use ($sv) {
                $q->where('notes', 'like', "%{$sv}%");
            });
        }
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumn = $params->orderColumn ?? 'ledger_entries.created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

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
        return LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $schoolId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function getLastEntryForTherapist(int $therapistId): ?LedgerEntry
    {
        return LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapistId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function getSchoolStats(int $schoolId): array
    {
        $ledgerQuery = LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $schoolId);

        $totalInvoiced = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::INVOICE_GENERATED)
            ->sum('amount');

        $totalPaid = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_RECEIVED)
            ->sum('amount');

        $invoiceCount = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::INVOICE_GENERATED)
            ->count();

        $paymentCount = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_RECEIVED)
            ->count();

        $lastEntry = (clone $ledgerQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;
        $transactionCount = $ledgerQuery->count();

        $totalInvoicedFloat = (float) $totalInvoiced;
        $totalPaidFloat = (float) $totalPaid;

        return [
            'total_invoiced' => $totalInvoicedFloat,
            'total_paid' => $totalPaidFloat,
            'outstanding' => $totalInvoicedFloat - $totalPaidFloat,
            'invoice_count' => (int) $invoiceCount,
            'payment_count' => (int) $paymentCount,
            'current_balance' => $currentBalance,
            'transaction_count' => (int) $transactionCount,
        ];
    }

    public function getTherapistStats(int $therapistId): array
    {
        $ledgerQuery = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapistId);

        $totalBilled = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::BILL_GENERATED)
            ->sum('amount');

        $totalPaid = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_MADE)
            ->sum('amount');

        $billCount = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::BILL_GENERATED)
            ->count();

        $paymentCount = (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_MADE)
            ->count();

        $lastEntry = (clone $ledgerQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;
        $transactionCount = $ledgerQuery->count();

        $totalBilledFloat = (float) $totalBilled;
        $totalPaidFloat = (float) $totalPaid;

        return [
            'total_billed' => $totalBilledFloat,
            'total_paid' => $totalPaidFloat,
            'outstanding' => $totalBilledFloat - $totalPaidFloat,
            'bill_count' => (int) $billCount,
            'payment_count' => (int) $paymentCount,
            'current_balance' => $currentBalance,
            'transaction_count' => (int) $transactionCount,
        ];
    }
}
