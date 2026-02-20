<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\Domain\Finance\Repositories\TherapistBillPaymentRepositoryInterface;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TherapistBillPaymentService
{
    public function __construct(
        private readonly TherapistBillPaymentRepositoryInterface $payments,
        private readonly LedgerEntryRepositoryInterface $ledgerEntries,
    ) {}

    public function recordPayment(RecordTherapistBillPaymentDTO $dto): TherapistBillPayment
    {
        if ($dto->therapistBillId <= 0) {
            throw new \RuntimeException('Cannot record payment without a therapist bill.');
        }

        return DB::transaction(function () use ($dto) {
            $bill = TherapistBill::findOrFail($dto->therapistBillId);
            $therapistId = $bill->therapist_id;

            $paymentData = $dto->toArray();
            $paymentData['therapist_id'] = $therapistId;
            $paymentData['therapist_bill_id'] = $bill->id;

            $payment = $this->payments->createPayment($paymentData);

            $this->payments->createAllocation([
                'therapist_bill_id' => $bill->id,
                'therapist_bill_payment_id' => $payment->id,
                'allocated_amount' => $dto->amount,
            ]);

            $this->createLedgerEntry($payment, $therapistId);

            return $payment->load('allocations', 'recordedBy', 'therapistBill');
        });
    }

    protected function createLedgerEntry(TherapistBillPayment $payment, int $therapistId): void
    {
        $lastEntry = $this->ledgerEntries->getLastEntryForTherapist($therapistId);
        $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;

        $newBalance = $previousBalance - (float) $payment->amount;

        LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $therapistId,
            'transaction_type' => TransactionType::PAYMENT_MADE,
            'amount' => (float) $payment->amount,
            'balance_after' => $newBalance,
            'reference_type' => TherapistBillPayment::class,
            'reference_id' => $payment->id,
            'notes' => 'Payment made',
            'recorded_by_id' => $payment->recorded_by_id,
        ]);
    }

    public function deletePayment(TherapistBillPayment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $this->payments->deleteAllocationsForPayment($payment);
            $this->payments->softDeletePayment($payment);

            LedgerEntry::where('reference_type', TherapistBillPayment::class)
                ->where('reference_id', $payment->id)
                ->delete();

            return true;
        });
    }
}
