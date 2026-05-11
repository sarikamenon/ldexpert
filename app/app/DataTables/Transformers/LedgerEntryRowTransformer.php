<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;

final class LedgerEntryRowTransformer
{
    /**
     * @return array<int, string> 9 cell HTML strings
     */
    public static function transform(LedgerEntry $entry): array
    {
        $dateCell = '<div class="whitespace-nowrap">'.$entry->recorded_at->format('M d, Y').'</div>'
            .'<div class="text-xs text-foreground/60 whitespace-nowrap">'.$entry->recorded_at->format(config('display.time')).'</div>';

        $variant = match ($entry->transaction_type->value) {
            'invoice_generated', 'bill_generated' => 'primary',
            'payment_received', 'credit_note' => 'success',
            'payment_made', 'refund' => 'danger',
            default => 'secondary',
        };
        $badgeClass = match ($variant) {
            'primary' => 'bg-primary/10 text-primary border border-primary/20',
            'success' => 'bg-success/10 text-success border border-success/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-secondary/10 text-secondary border border-secondary/20',
        };
        $typeCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium whitespace-nowrap '.$badgeClass.'">'
            .e($entry->transaction_type->label()).'</span>';

        $refCell = self::renderReferenceCell($entry);

        $isDebit = in_array($entry->transaction_type->value, ['invoice_generated', 'bill_generated', 'refund'], true);

        $debitCell = $isDebit
            ? '<span class="font-semibold text-danger-600">$'.number_format(abs((float) $entry->amount), 2).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $creditCell = ! $isDebit
            ? '<span class="font-semibold text-success-600">$'.number_format(abs((float) $entry->amount), 2).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $balance = (float) $entry->balance_after;
        $balanceCell = '<span class="font-semibold '.($balance > 0 ? 'text-danger-600' : 'text-success-600').'">'
            .'$'.number_format(abs($balance), 2).($balance > 0 ? ' DR' : ' CR').'</span>';

        $notesCell = $entry->notes
            ? '<span class="text-foreground/80">'.e(\Illuminate\Support\Str::limit($entry->notes, 60)).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $recordedByCell = e($entry->recordedBy->name ?? 'System');

        $type = $entry->transaction_type;
        $isAdjustment = $type === TransactionType::CREDIT_NOTE || $type === TransactionType::REFUND;
        if ($isAdjustment) {
            $editUrl = route('admin.ledger.adjustment.show', ['entry' => $entry->id]);
            $deleteUrl = route('admin.ledger.adjustment.destroy', ['entry' => $entry->id]);
            $actionsCell = ActionButtons::wrap(
                ActionButtons::edit($editUrl, 'Edit adjustment', [
                    'data-edit-adjustment' => '1',
                    'data-entry-id' => $entry->id,
                    'data-fetch-url' => $editUrl,
                ]),
                ActionButtons::delete(
                    $deleteUrl,
                    'Delete adjustment',
                    'Delete adjustment?',
                    'This will remove the adjustment and recompute later balances. This cannot be undone.',
                ),
            );
        } else {
            $actionsCell = '<span class="text-foreground/30">—</span>';
        }

        return [
            $dateCell,
            $typeCell,
            $refCell,
            $debitCell,
            $creditCell,
            $balanceCell,
            $notesCell,
            $recordedByCell,
            $actionsCell,
        ];
    }

    /**
     * Render the "Reference" column. Relies on eager-loaded relations
     * (`reference` + nested `invoice`/`therapistBill` for payment morph types)
     * to avoid per-row queries.
     */
    private static function renderReferenceCell(LedgerEntry $entry): string
    {
        $ref = $entry->reference;
        if ($ref === null) {
            return '<span class="text-foreground/40">—</span>';
        }

        $refType = $entry->reference_type;

        if ($ref instanceof Invoice && $refType === Invoice::class) {
            return self::invoiceLink($ref);
        }
        if ($ref instanceof TherapistBill && $refType === TherapistBill::class) {
            return self::therapistBillLink($ref);
        }
        if ($ref instanceof InvoicePayment && $refType === InvoicePayment::class) {
            return self::invoicePaymentLink($ref, $entry->reference_id);
        }
        if ($ref instanceof TherapistBillPayment && $refType === TherapistBillPayment::class) {
            return self::therapistBillPaymentLink($ref, $entry->reference_id);
        }

        return e(class_basename($refType ?? '')).' #'.($entry->reference_id ?? '');
    }

    private static function invoiceLink(Invoice $invoice): string
    {
        return '<a href="'.e(route('admin.invoices.show', ['invoice' => $invoice->id])).'" class="text-primary hover:underline">Invoice #'
            .e($invoice->invoice_number ?? (string) $invoice->id).'</a>';
    }

    private static function therapistBillLink(TherapistBill $bill): string
    {
        return '<a href="'.e(route('admin.billing.therapist-bills.show', ['bill' => $bill->id])).'" class="text-primary hover:underline">Bill #'
            .e($bill->bill_number ?? (string) $bill->id).'</a>';
    }

    private static function invoicePaymentLink(InvoicePayment $payment, ?int $fallbackId): string
    {
        $invoiceId = $payment->invoice?->id;

        return $invoiceId
            ? '<a href="'.e(route('admin.invoices.show', ['invoice' => $invoiceId])).'" class="text-primary hover:underline">Payment #'.$payment->id.'</a>'
            : 'Payment #'.($fallbackId ?? 'N/A');
    }

    private static function therapistBillPaymentLink(TherapistBillPayment $payment, ?int $fallbackId): string
    {
        $billId = $payment->therapistBill?->id;

        return $billId
            ? '<a href="'.e(route('admin.billing.therapist-bills.show', ['bill' => $billId])).'" class="text-primary hover:underline">Payment #'.$payment->id.'</a>'
            : 'Payment #'.($fallbackId ?? 'N/A');
    }
}
