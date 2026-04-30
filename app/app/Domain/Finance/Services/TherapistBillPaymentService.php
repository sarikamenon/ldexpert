<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Repositories\TherapistBillPaymentRepositoryInterface;
use App\DTOs\CreateExpenseDTO;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\TransactionType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TherapistBillPaymentService
{
    public function __construct(
        private readonly TherapistBillPaymentRepositoryInterface $payments,
        private readonly ExpenseService $expenses,
        private readonly LedgerService $ledgerService,
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
            // Combine the user-selected date with the current time so same-day payments
            // get distinct timestamps and can be ordered correctly for YTD calculation.
            $paymentData['paid_at'] = now()->setDateFrom(Carbon::parse($dto->paidAt))->toDateTimeString();

            $payment = $this->payments->createPayment($paymentData);

            $this->payments->createAllocation([
                'therapist_bill_id' => $bill->id,
                'therapist_bill_payment_id' => $payment->id,
                'allocated_amount' => $dto->amount,
            ]);

            $this->createLedgerEntry($payment, $therapistId);

            $this->createLinkedExpense($payment, $bill);

            return $payment->load('allocations', 'recordedBy', 'therapistBill');
        });
    }

    protected function createLinkedExpense(TherapistBillPayment $payment, TherapistBill $bill): void
    {
        $categoryId = (int) config('expenses.protected_categories.therapist-payouts');
        $category = ExpenseCategory::find($categoryId);
        if ($category === null) {
            throw new RuntimeException("Expense category #{$categoryId} (therapist payouts) is missing. Run migrations.");
        }

        /** @var User $therapist */
        $therapist = $bill->therapist;
        $therapistName = $therapist->name;

        $dto = new CreateExpenseDTO(
            expenseCategoryId: (int) $category->id,
            expenseDate: $payment->paid_at->format('Y-m-d'),
            amount: (float) $payment->amount,
            vendorPayee: $therapistName,
            description: "Payment for therapist bill #{$bill->id}",
            reference: $payment->reference,
            createdById: $payment->recorded_by_id,
        );

        $this->expenses->createExpenseFromSource($dto, $payment);
    }

    protected function createLedgerEntry(TherapistBillPayment $payment, int $therapistId): void
    {
        // Stamp the picked date with the current time-of-day so the entry sorts
        // deterministically and doesn't collapse to 00:00:00. See LEDGER_SYSTEM.md.
        $this->ledgerService->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapistId,
            type: TransactionType::PAYMENT_MADE,
            amount: (float) $payment->amount,
            recordedAt: LedgerService::resolveDateOnlyRecordedAt($payment->paid_at),
            referenceType: TherapistBillPayment::class,
            referenceId: $payment->id,
            notes: 'Payment made',
            recordedById: $payment->recorded_by_id,
        );
    }

    public function deletePayment(TherapistBillPayment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $this->payments->deleteAllocationsForPayment($payment);
            $this->payments->softDeletePayment($payment);

            Expense::forSource($payment)->delete();

            // Soft-delete the ledger row and recompute the chain so any later rows
            // have correct balance_after. Pre-step-5 behaviour hard-deleted the row
            // and left stale balances on every later entry.
            $entries = LedgerEntry::forReference($payment)->get();
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
