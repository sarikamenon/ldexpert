<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\RecordInvoicePaymentDTO;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoicePaymentAllocation;
use App\Models\LedgerEntry;
use App\Models\School;
use Illuminate\Support\Facades\DB;

class InvoicePaymentService
{
    public function recordPayment(RecordInvoicePaymentDTO $dto): InvoicePayment
    {
        return DB::transaction(function () use ($dto) {
            $startingInvoice = null;
            $schoolId = null;

            if ($dto->invoiceId > 0) {
                // Starting invoice determines the school and initial context
                $startingInvoice = Invoice::findOrFail($dto->invoiceId);

                if (! $startingInvoice->school_id) {
                    throw new \RuntimeException('Cannot record payment for an invoice without a school.');
                }

                $schoolId = $startingInvoice->school_id;
            } else {
                $schoolId = $dto->schoolId;
            }

            if (! $schoolId) {
                throw new \RuntimeException('Cannot record payment without a school.');
            }

            // Create the payment receipt (lump-sum payment)
            $paymentData = $dto->toArray();
            $paymentData['school_id'] = $schoolId;

            $payment = InvoicePayment::create($paymentData);

            $remainingPayment = $dto->amount;

            // Oldest-first invoices for this school
            /** @var \Illuminate\Support\Collection<int, Invoice> $invoices */
            $invoices = Invoice::where('school_id', $schoolId)
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $affectedInvoices = collect();

            foreach ($invoices as $invoice) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $alreadyAllocated = (float) $invoice->paymentAllocations()->sum('allocated_amount');
                $remainingOnInvoice = max(0, (float) $invoice->total - $alreadyAllocated);

                if ($remainingOnInvoice <= 0) {
                    continue;
                }

                $allocationAmount = min($remainingOnInvoice, $remainingPayment);

                InvoicePaymentAllocation::create([
                    'invoice_id' => $invoice->id,
                    'invoice_payment_id' => $payment->id,
                    'allocated_amount' => $allocationAmount,
                ]);

                $remainingPayment -= $allocationAmount;
                $affectedInvoices->push($invoice);
            }

            // Create a single ledger entry for the school based on the full receipt amount
            $this->createLedgerEntry($payment, $schoolId);

            return $payment->load('allocations', 'recordedBy');
        });
    }

    protected function createLedgerEntry(InvoicePayment $payment, int $schoolId): void
    {
        // Get current balance for the school
        $lastEntry = LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $schoolId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0;

        $newBalance = $previousBalance - (float) $payment->amount;

        LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $schoolId,
            'transaction_type' => TransactionType::PAYMENT_RECEIVED,
            'amount' => (float) $payment->amount,
            'balance_after' => $newBalance,
            'reference_type' => InvoicePayment::class,
            'reference_id' => $payment->id,
            'notes' => 'Payment received',
            'recorded_by_id' => $payment->recorded_by_id,
        ]);
    }

    public function deletePayment(InvoicePayment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            // Delete allocations (will also adjust totals for each invoice)
            $payment->allocations()->delete();

            // Delete the payment
            $payment->delete();

            // Delete associated ledger entries
            LedgerEntry::where('reference_type', InvoicePayment::class)
                ->where('reference_id', $payment->id)
                ->delete();

            return true;
        });
    }
}
