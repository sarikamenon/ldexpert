<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Repositories\InvoicePaymentRepositoryInterface;
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
        private readonly LedgerService $ledgerService,
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
        // paid_at is a date column on invoice_payments. Stamp the picked date
        // with the current time-of-day so the entry sorts deterministically and
        // doesn't collapse to 00:00:00. See LEDGER_SYSTEM.md.
        $this->ledgerService->createEntry(
            ledgerableType: School::class,
            ledgerableId: $schoolId,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: (float) $payment->amount,
            recordedAt: LedgerService::resolveDateOnlyRecordedAt($payment->paid_at),
            referenceType: InvoicePayment::class,
            referenceId: $payment->id,
            notes: 'Payment received',
            recordedById: $payment->recorded_by_id,
        );
    }

    public function deletePayment(InvoicePayment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $this->payments->deleteAllocationsForPayment($payment);
            $this->payments->softDeletePayment($payment);

            // Soft-delete the ledger row (so it stops contributing to balance) and
            // recompute the chain so any later rows have correct balance_after.
            // Pre-step-5 behaviour was hard-delete + leaving stale balances downstream.
            $entries = LedgerEntry::where('reference_type', InvoicePayment::class)
                ->where('reference_id', $payment->id)
                ->get();

            foreach ($entries as $entry) {
                $recordedAt = $entry->recorded_at;
                /** @var class-string $ledgerableType */
                $ledgerableType = $entry->ledgerable_type;
                $entry->delete();
                $this->ledgerService->recomputeChainFrom(
                    $ledgerableType,
                    (int) $entry->ledgerable_id,
                    $recordedAt,
                );
            }

            return true;
        });
    }
}
