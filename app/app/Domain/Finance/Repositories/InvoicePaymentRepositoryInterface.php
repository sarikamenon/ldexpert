<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoicePaymentAllocation;
use Illuminate\Support\Collection;

interface InvoicePaymentRepositoryInterface
{
    public function createPayment(array $data): InvoicePayment;

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoicesForSchoolOldestFirst(int $schoolId): Collection;

    public function createAllocation(array $data): InvoicePaymentAllocation;

    public function deleteAllocationsForPayment(InvoicePayment $payment): void;

    public function softDeletePayment(InvoicePayment $payment): void;
}

