<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\LedgerEntry;

final class LedgerEntryRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings
     */
    public static function transform(LedgerEntry $entry): array
    {
        $dateCell = '<div>'.$entry->created_at->format('M d, Y').'</div>'
            .'<div class="text-xs text-foreground/60">'.$entry->created_at->format('h:i A').'</div>';

        $variant = match ($entry->transaction_type->value) {
            'invoice_generated', 'bill_generated' => 'primary',
            'payment_received' => 'success',
            'payment_made' => 'danger',
            default => 'secondary',
        };
        $badgeClass = match ($variant) {
            'primary' => 'bg-primary/10 text-primary border border-primary/20',
            'success' => 'bg-success/10 text-success border border-success/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-secondary/10 text-secondary border border-secondary/20',
        };
        $typeCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'
            .e($entry->transaction_type->label()).'</span>';

        /** @var \Illuminate\Database\Eloquent\Model|null $ref */
        $ref = $entry->reference;
        $refType = $entry->reference_type;
        if ($ref) {
            if ($refType === 'App\\Models\\Invoice') {
                /** @var \App\Models\Invoice $ref */
                $refCell = '<a href="'.e(route('admin.invoices.show', ['invoice' => $ref->id])).'" class="text-primary hover:underline">Invoice #'.e($ref->invoice_number ?? (string) $ref->id).'</a>';
            } elseif ($refType === 'App\\Models\\TherapistBill') {
                /** @var \App\Models\TherapistBill $ref */
                $refCell = '<a href="'.e(route('admin.billing.therapist-bills.show', ['bill' => $ref->id])).'" class="text-primary hover:underline">Bill #'.e($ref->bill_number ?? (string) $ref->id).'</a>';
            } elseif ($refType === 'App\\Models\\InvoicePayment') {
                /** @var \App\Models\InvoicePayment $ref */
                $invoiceFromPayment = $ref->invoice()->first()?->id;
                $refCell = $invoiceFromPayment
                    ? '<a href="'.e(route('admin.invoices.show', ['invoice' => $invoiceFromPayment])).'" class="text-primary hover:underline">Payment #'.$ref->id.'</a>'
                    : 'Payment #'.($entry->reference_id ?? 'N/A');
            } elseif ($refType === 'App\\Models\\TherapistBillPayment') {
                /** @var \App\Models\TherapistBillPayment $ref */
                $billFromPayment = $ref->therapistBill()->first()?->id;
                $refCell = $billFromPayment
                    ? '<a href="'.e(route('admin.billing.therapist-bills.show', ['bill' => $billFromPayment])).'" class="text-primary hover:underline">Payment #'.$ref->id.'</a>'
                    : 'Payment #'.($entry->reference_id ?? 'N/A');
            } else {
                $refCell = e(class_basename($refType ?? '')).' #'.($entry->reference_id ?? '');
            }
        } else {
            $refCell = '<span class="text-foreground/40">—</span>';
        }

        $isDebit = in_array($entry->transaction_type->value, ['invoice_generated', 'bill_generated'], true);

        $debitCell = $isDebit
            ? '<span class="font-semibold text-danger-600">$'.number_format(abs((float) $entry->amount), 2).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $creditCell = ! $isDebit
            ? '<span class="font-semibold text-success-600">$'.number_format(abs((float) $entry->amount), 2).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $balance = (float) $entry->balance_after;
        $balanceCell = '<span class="font-semibold '.($balance >= 0 ? 'text-success-600' : 'text-danger-600').'">'
            .'$'.number_format(abs($balance), 2).($balance < 0 ? ' DR' : ' CR').'</span>';

        $notesCell = $entry->notes
            ? '<span class="text-foreground/80">'.e(\Illuminate\Support\Str::limit($entry->notes, 60)).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $recordedByCell = e($entry->recordedBy->name ?? 'System');

        return [
            $dateCell,
            $typeCell,
            $refCell,
            $debitCell,
            $creditCell,
            $balanceCell,
            $notesCell,
            $recordedByCell,
        ];
    }
}
