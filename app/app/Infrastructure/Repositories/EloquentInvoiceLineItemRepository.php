<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Billing\Repositories\InvoiceLineItemRepositoryInterface;
use App\Enums\BillingMode;
use App\Enums\InvoiceLineType;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentInvoiceLineItemRepository implements InvoiceLineItemRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return Collection<int, InvoiceLineItem>
     */
    public function createMany(Invoice $invoice, array $lineItems): Collection
    {
        $created = collect();

        foreach ($lineItems as $item) {
            $item['invoice_id'] = $invoice->id;
            $created->push(InvoiceLineItem::create($item));
        }

        /** @var Collection<int, InvoiceLineItem> $created */
        return $created;
    }

    public function deleteForInvoice(int $invoiceId): void
    {
        InvoiceLineItem::query()
            ->where('invoice_id', $invoiceId)
            ->delete();
    }

    /**
     * @return Collection<int, InvoiceLineItem>
     */
    public function getForInvoice(int $invoiceId): Collection
    {
        /** @var Collection<int, InvoiceLineItem> $result */
        $result = InvoiceLineItem::query()
            ->where('invoice_id', $invoiceId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, InvoiceLineItem>
     */
    public function getAdvanceLinesForPeriod(int $invoiceId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        /** @var Collection<int, InvoiceLineItem> $result */
        $result = InvoiceLineItem::query()
            ->where('invoice_id', $invoiceId)
            ->where('line_type', InvoiceLineType::ADVANCE_SCHEDULED->value)
            ->where('billing_period_start', $periodStart->toDateString())
            ->where('billing_period_end', $periodEnd->toDateString())
            ->with(['schedule', 'sessionLog'])
            ->orderBy('sort_order')
            ->get();

        return $result;
    }

    public function getPreviousAdvanceInvoice(int $schoolId, ?int $currentInvoiceId = null): ?Invoice
    {
        $query = Invoice::query()
            ->where('school_id', $schoolId)
            ->where('billing_mode', BillingMode::ADVANCE->value)
            ->orderByDesc('billing_period_end')
            ->orderByDesc('id');

        if ($currentInvoiceId !== null) {
            $query->where('id', '<>', $currentInvoiceId);
        }

        return $query->first();
    }
}
