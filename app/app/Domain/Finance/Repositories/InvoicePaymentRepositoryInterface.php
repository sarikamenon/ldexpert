<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\InvoicePayment;
use App\Models\InvoicePaymentAllocation;

interface InvoicePaymentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function createPayment(array $data): InvoicePayment;

    /** @param array<string, mixed> $data */
    public function createAllocation(array $data): InvoicePaymentAllocation;

    public function deleteAllocationsForPayment(InvoicePayment $payment): void;

    public function softDeletePayment(InvoicePayment $payment): void;
}
