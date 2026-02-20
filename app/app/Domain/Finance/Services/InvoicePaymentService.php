<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Repositories\InvoicePaymentRepositoryInterface;
use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\DTOs\RecordInvoicePaymentDTO;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\School;
use Illuminate\Support\Facades\DB;

class InvoicePaymentService
{
    public function __construct(
        private readonly InvoicePaymentRepositoryInterface $payments,
        private readonly LedgerEntryRepositoryInterface $ledgerEntries,
    ) {}

    public function recordPayment(RecordInvoicePaymentDTO $dto): InvoicePayment
    {
        if ($dto->invoiceId <= 0) {
            throw new \RuntimeException('Cannot record payment without an invoice.');
        }

        return DB::transaction(function () use ($dto) {
            $invoice = Invoice::findOrFail($dto->invoiceId);

            if (! $invoice->school_id) {
                throw new \RuntimeException('Cannot record payment for an invoice without a school.');
            }

            $schoolId = $invoice->school_id;

            $paymentData = $dto->toArray();
            $paymentData['school_id'] = $schoolId;
            $paymentData['invoice_id'] = $invoice->id;

            $payment = $this->payments->createPayment($paymentData);

            $this->payments->createAllocation([
                'invoice_id' => $invoice->id,
                'invoice_payment_id' => $payment->id,
                'allocated_amount' => $dto->amount,
            ]);

            $this->createLedgerEntry($payment, $schoolId);

            return $payment->load('allocations', 'recordedBy', 'invoice');
        });
    }

    protected function createLedgerEntry(InvoicePayment $payment, int $schoolId): void
    {
        $lastEntry = $this->ledgerEntries->getLastEntryForSchool($schoolId);
        $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;

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
            $this->payments->deleteAllocationsForPayment($payment);
            $this->payments->softDeletePayment($payment);

            LedgerEntry::where('reference_type', InvoicePayment::class)
                ->where('reference_id', $payment->id)
                ->delete();

            return true;
        });
    }
}
