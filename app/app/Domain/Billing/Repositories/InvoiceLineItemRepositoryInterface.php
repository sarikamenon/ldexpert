<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface InvoiceLineItemRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return Collection<int, InvoiceLineItem>
     */
    public function createMany(Invoice $invoice, array $lineItems): Collection;

    /**
     * @return Collection<int, InvoiceLineItem>
     */
    public function getForInvoice(int $invoiceId): Collection;

    /**
     * Delete all line items for an invoice (used when re-selecting an advance draft).
     */
    public function deleteForInvoice(int $invoiceId): void;

    /**
     * @return Collection<int, InvoiceLineItem>
     */
    public function getAdvanceLinesForPeriod(int $invoiceId, Carbon $periodStart, Carbon $periodEnd): Collection;

    public function getPreviousAdvanceInvoice(int $schoolId, ?int $currentInvoiceId = null): ?Invoice;
}
