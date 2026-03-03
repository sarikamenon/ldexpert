<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Enums\InvoiceStatus;
use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\TherapistBill;
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

        // Calculate total invoiced from invoices table (source of truth)
        $totalInvoiced = (float) Invoice::where('school_id', $schoolId)
            ->whereIn('status', [InvoiceStatus::SENT->value, InvoiceStatus::PAID->value])
            ->sum('total');

        $totalPaid = (float) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_RECEIVED)
            ->sum('amount');

        $invoiceCount = Invoice::where('school_id', $schoolId)
            ->whereIn('status', [InvoiceStatus::SENT->value, InvoiceStatus::PAID->value])
            ->count();

        $paymentCount = (int) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_RECEIVED)
            ->count();

        $lastEntry = (clone $ledgerQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;
        $transactionCount = $ledgerQuery->count();

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding' => $totalInvoiced - $totalPaid,
            'invoice_count' => $invoiceCount,
            'payment_count' => $paymentCount,
            'current_balance' => $currentBalance,
            'transaction_count' => (int) $transactionCount,
        ];
    }

    public function getTherapistStats(int $therapistId): array
    {
        $ledgerQuery = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapistId);

        // Calculate total billed from therapist_bills table (source of truth)
        $totalBilled = (float) TherapistBill::where('therapist_id', $therapistId)
            ->whereIn('status', [TherapistBillStatus::SENT->value, TherapistBillStatus::PAID->value])
            ->sum('total_due');

        $totalPaid = (float) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_MADE)
            ->sum('amount');

        $billCount = TherapistBill::where('therapist_id', $therapistId)
            ->whereIn('status', [TherapistBillStatus::SENT->value, TherapistBillStatus::PAID->value])
            ->count();

        $paymentCount = (int) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::PAYMENT_MADE)
            ->count();

        $lastEntry = (clone $ledgerQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;
        $transactionCount = $ledgerQuery->count();

        return [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'outstanding' => $totalBilled - $totalPaid,
            'bill_count' => $billCount,
            'payment_count' => $paymentCount,
            'current_balance' => $currentBalance,
            'transaction_count' => (int) $transactionCount,
        ];
    }
}
