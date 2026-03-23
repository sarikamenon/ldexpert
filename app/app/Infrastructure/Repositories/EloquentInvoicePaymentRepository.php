<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\InvoicePaymentRepositoryInterface;
use App\Models\InvoicePayment;
use App\Models\InvoicePaymentAllocation;

final class EloquentInvoicePaymentRepository implements InvoicePaymentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function createPayment(array $data): InvoicePayment
    {
        return InvoicePayment::create($data);
    }

    /** @param array<string, mixed> $data */
    public function createAllocation(array $data): InvoicePaymentAllocation
    {
        return InvoicePaymentAllocation::create($data);
    }

    public function deleteAllocationsForPayment(InvoicePayment $payment): void
    {
        $payment->allocations()->delete();
    }

    public function softDeletePayment(InvoicePayment $payment): void
    {
        $payment->delete();
    }
}
