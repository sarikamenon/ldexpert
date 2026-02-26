<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\TherapistBillPayment;
use App\Models\TherapistBillPaymentAllocation;

interface TherapistBillPaymentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function createPayment(array $data): TherapistBillPayment;

    /** @param array<string, mixed> $data */
    public function createAllocation(array $data): TherapistBillPaymentAllocation;

    public function deleteAllocationsForPayment(TherapistBillPayment $payment): void;

    public function softDeletePayment(TherapistBillPayment $payment): void;
}
