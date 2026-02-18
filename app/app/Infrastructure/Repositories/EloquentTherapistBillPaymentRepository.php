<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\TherapistBillPaymentRepositoryInterface;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\TherapistBillPaymentAllocation;
use Illuminate\Support\Collection;

final class EloquentTherapistBillPaymentRepository implements TherapistBillPaymentRepositoryInterface
{
    public function createPayment(array $data): TherapistBillPayment
    {
        return TherapistBillPayment::create($data);
    }

    public function getBillsForTherapistOldestFirst(int $therapistId): Collection
    {
        return TherapistBill::where('therapist_id', $therapistId)
            ->orderBy('bill_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function createAllocation(array $data): TherapistBillPaymentAllocation
    {
        return TherapistBillPaymentAllocation::create($data);
    }

    public function deleteAllocationsForPayment(TherapistBillPayment $payment): void
    {
        $payment->allocations()->delete();
    }

    public function softDeletePayment(TherapistBillPayment $payment): void
    {
        $payment->delete();
    }
}

