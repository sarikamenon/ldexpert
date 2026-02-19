<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\InvoicePaymentRepositoryInterface;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoicePaymentAllocation;
use Illuminate\Support\Collection;

final class EloquentInvoicePaymentRepository implements InvoicePaymentRepositoryInterface
{
    public function createPayment(array $data): InvoicePayment
    {
        return InvoicePayment::create($data);
    }

    public function getInvoicesForSchoolOldestFirst(int $schoolId): Collection
    {
        return Invoice::where('school_id', $schoolId)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

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

