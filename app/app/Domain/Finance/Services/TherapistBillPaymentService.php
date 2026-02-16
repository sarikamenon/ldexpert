<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\TherapistBillPaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TherapistBillPaymentService
{
    public function recordPayment(RecordTherapistBillPaymentDTO $dto): TherapistBillPayment
    {
        return DB::transaction(function () use ($dto) {
            $startingBill = null;
            $therapistId = null;

            if ($dto->therapistBillId > 0) {
                // Starting bill determines the therapist and initial context
                $startingBill = TherapistBill::findOrFail($dto->therapistBillId);
                $therapistId = $startingBill->therapist_id;
            } else {
                $therapistId = $dto->therapistId;
            }

            if (! $therapistId) {
                throw new \RuntimeException('Cannot record payment without a therapist.');
            }

            // Create the payment receipt (lump-sum payment)
            $paymentData = $dto->toArray();
            $paymentData['therapist_id'] = $therapistId;

            $payment = TherapistBillPayment::create($paymentData);

            $remainingPayment = $dto->amount;

            // Oldest-first bills for this therapist
            /** @var \Illuminate\Support\Collection<int, TherapistBill> $bills */
            $bills = TherapistBill::where('therapist_id', $therapistId)
                ->orderBy('bill_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $affectedBills = collect();

            foreach ($bills as $bill) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $alreadyAllocated = (float) $bill->paymentAllocations()->sum('allocated_amount');
                $remainingOnBill = max(0, (float) $bill->total_due - $alreadyAllocated);

                if ($remainingOnBill <= 0) {
                    continue;
                }

                $allocationAmount = min($remainingOnBill, $remainingPayment);

                TherapistBillPaymentAllocation::create([
                    'therapist_bill_id' => $bill->id,
                    'therapist_bill_payment_id' => $payment->id,
                    'allocated_amount' => $allocationAmount,
                ]);

                $remainingPayment -= $allocationAmount;
                $affectedBills->push($bill);
            }

            // Create a single ledger entry for the therapist based on the full receipt amount
            $this->createLedgerEntry($payment, $therapistId);

            return $payment->load('allocations', 'recordedBy');
        });
    }

    protected function createLedgerEntry(TherapistBillPayment $payment, int $therapistId): void
    {
        // Get current balance for the therapist
        $lastEntry = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapistId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0;

        // Payment made reduces the therapist's balance (we owe them less)
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
            // Delete allocations
            $payment->allocations()->delete();

            // Delete the payment
            $payment->delete();

            // Delete associated ledger entries
            LedgerEntry::where('reference_type', TherapistBillPayment::class)
                ->where('reference_id', $payment->id)
                ->delete();

            return true;
        });
    }
}
