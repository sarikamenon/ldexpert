<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Create a ledger entry when an invoice is generated.
     */
    public function createInvoiceGeneratedEntry(Invoice $invoice): ?LedgerEntry
    {
        if (! $invoice->school_id) {
            return null;
        }

        return DB::transaction(function () use ($invoice) {
            // Get current balance for the school
            $lastEntry = LedgerEntry::where('ledgerable_type', School::class)
                ->where('ledgerable_id', $invoice->school_id)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0;

            // Invoice increases the school's balance (they owe more)
            $newBalance = $previousBalance + (float) $invoice->total;

            return LedgerEntry::create([
                'ledgerable_type' => School::class,
                'ledgerable_id' => $invoice->school_id,
                'transaction_type' => TransactionType::INVOICE_GENERATED,
                'amount' => (float) $invoice->total,
                'balance_after' => $newBalance,
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
                'notes' => 'Invoice generated: '.$invoice->invoice_number,
                'recorded_by_id' => $invoice->sent_by_id,
            ]);
        });
    }

    /**
     * Create a ledger entry when a therapist bill is generated.
     */
    public function createBillGeneratedEntry(TherapistBill $bill): LedgerEntry
    {
        return DB::transaction(function () use ($bill) {
            // Get current balance for the therapist
            $lastEntry = LedgerEntry::where('ledgerable_type', User::class)
                ->where('ledgerable_id', $bill->therapist_id)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0;

            // Bill increases the therapist's balance (we owe them more)
            $newBalance = $previousBalance + (float) $bill->total_due;

            return LedgerEntry::create([
                'ledgerable_type' => User::class,
                'ledgerable_id' => $bill->therapist_id,
                'transaction_type' => TransactionType::BILL_GENERATED,
                'amount' => (float) $bill->total_due,
                'balance_after' => $newBalance,
                'reference_type' => TherapistBill::class,
                'reference_id' => $bill->id,
                'notes' => 'Bill generated: '.$bill->bill_number,
                'recorded_by_id' => $bill->sent_by_id,
            ]);
        });
    }

    /**
     * Get ledger balance for a school.
     */
    public function getSchoolBalance(int $schoolId): float
    {
        $lastEntry = LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $schoolId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (float) $lastEntry->balance_after : 0;
    }

    /**
     * Get ledger balance for a therapist.
     */
    public function getTherapistBalance(int $therapistId): float
    {
        $lastEntry = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapistId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (float) $lastEntry->balance_after : 0;
    }
}
