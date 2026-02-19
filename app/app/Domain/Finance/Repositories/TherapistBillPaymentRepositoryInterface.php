<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\TherapistBillPaymentAllocation;
use Illuminate\Support\Collection;

interface TherapistBillPaymentRepositoryInterface
{
    public function createPayment(array $data): TherapistBillPayment;

    /**
     * @return Collection<int, TherapistBill>
     */
    public function getBillsForTherapistOldestFirst(int $therapistId): Collection;

    public function createAllocation(array $data): TherapistBillPaymentAllocation;

    public function deleteAllocationsForPayment(TherapistBillPayment $payment): void;

    public function softDeletePayment(TherapistBillPayment $payment): void;
}

