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
        return LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $schoolId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    public function getLastEntryForTherapist(int $therapistId): ?LedgerEntry
    {
        return LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapistId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
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

        $totalCreditNotes = (float) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::CREDIT_NOTE)
            ->sum('amount');

        $creditNoteCount = (int) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::CREDIT_NOTE)
            ->count();

        $totalRefunds = (float) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::REFUND)
            ->sum('amount');

        $refundCount = (int) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::REFUND)
            ->count();

        $lastEntry = (clone $ledgerQuery)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;
        $transactionCount = $ledgerQuery->count();

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding' => $totalInvoiced - $totalPaid - $totalCreditNotes + $totalRefunds,
            'invoice_count' => $invoiceCount,
            'payment_count' => $paymentCount,
            'total_credit_notes' => $totalCreditNotes,
            'credit_note_count' => $creditNoteCount,
            'total_refunds' => $totalRefunds,
            'refund_count' => $refundCount,
            'current_balance' => $currentBalance,
            'transaction_count' => (int) $transactionCount,
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

        $totalCreditNotes = (float) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::CREDIT_NOTE)
            ->sum('amount');

        $creditNoteCount = (int) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::CREDIT_NOTE)
            ->count();

        $totalRefunds = (float) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::REFUND)
            ->sum('amount');

        $refundCount = (int) (clone $ledgerQuery)
            ->where('transaction_type', TransactionType::REFUND)
            ->count();

        $lastEntry = (clone $ledgerQuery)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;
        $transactionCount = $ledgerQuery->count();

        return [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'outstanding' => $totalBilled - $totalPaid - $totalCreditNotes + $totalRefunds,
            'bill_count' => $billCount,
            'payment_count' => $paymentCount,
            'total_credit_notes' => $totalCreditNotes,
            'credit_note_count' => $creditNoteCount,
            'total_refunds' => $totalRefunds,
            'refund_count' => $refundCount,
            'current_balance' => $currentBalance,
            'transaction_count' => (int) $transactionCount,
        ];
    }
}
