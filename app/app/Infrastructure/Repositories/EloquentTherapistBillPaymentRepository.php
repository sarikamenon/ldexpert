<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\TherapistBillPaymentRepositoryInterface;
use App\Models\TherapistBillPayment;
use App\Models\TherapistBillPaymentAllocation;

final class EloquentTherapistBillPaymentRepository implements TherapistBillPaymentRepositoryInterface
{
    public function createPayment(array $data): TherapistBillPayment
    {
        return TherapistBillPayment::create($data);
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
