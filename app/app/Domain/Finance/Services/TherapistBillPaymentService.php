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
            // Starting bill determines the therapist and initial context
            $startingBill = TherapistBill::findOrFail($dto->therapistBillId);

            // Create the payment receipt (lump-sum payment)
            $payment = TherapistBillPayment::create($dto->toArray());

            $remainingPayment = $dto->amount;

            // Oldest-first unpaid bills for this therapist
            /** @var \Illuminate\Support\Collection<int, TherapistBill> $bills */
            $bills = TherapistBill::where('therapist_id', $startingBill->therapist_id)
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

            // Update statuses for all affected bills using allocation sums
            $affectedBills
                ->unique('id')
                ->each(function (TherapistBill $bill): void {
                    $this->updateBillStatus($bill);
                });

            // Create a single ledger entry for the therapist based on the full receipt amount
            $this->createLedgerEntry($payment, $startingBill->therapist_id);

            return $payment->load('allocations', 'recordedBy');
        });
    }

    protected function updateBillStatus(TherapistBill $bill): void
    {
        $totalPaid = (float) $bill->total_paid;
        $totalDue = (float) $bill->total_due;

        if ($totalPaid >= $totalDue) {
            $bill->status = TherapistBillStatus::PAID;
            if (! $bill->paid_at) {
                $bill->paid_at = now();
            }
        } elseif ($totalPaid > 0) {
            // Keep status as SENT if partially paid
            if ($bill->status === TherapistBillStatus::DRAFT) {
                $bill->status = TherapistBillStatus::SENT;
            }
        }

        $bill->save();
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
            // Capture affected bills before allocations are deleted
            $billIds = $payment->allocations()
                ->pluck('therapist_bill_id')
                ->unique()
                ->all();

            // Delete allocations
            $payment->allocations()->delete();

            // Delete the payment
            $payment->delete();

            // Recalculate bill statuses
            TherapistBill::whereIn('id', $billIds)->get()->each(function (TherapistBill $bill): void {
                $this->updateBillStatus($bill);
            });

            // Delete associated ledger entries
            LedgerEntry::where('reference_type', TherapistBillPayment::class)
                ->where('reference_id', $payment->id)
                ->delete();

            return true;
        });
    }
}
