<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\InvoicePayment;

final class InvoicePaymentRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(InvoicePayment $payment): array
    {
        $date = $payment->paid_at ? $payment->paid_at->format('M d, Y') : '—';

        $invoiceCell = '—';
        if ($payment->invoice) {
            $showUrl = route('admin.invoices.show', $payment->invoice);
            $schoolName = $payment->school?->name ?? $payment->invoice->school_name ?? '—';
            $invoiceCell = '<a href="'.e($showUrl).'" class="text-primary hover:underline">'.e($payment->invoice->invoice_number).'</a>'
                .' <span class="text-foreground/60">— '.e($schoolName).'</span>';
        }

        $amount = '$'.number_format((float) $payment->amount, 2);
        $methodLabel = $payment->method?->label() ?? '—';
        $methodBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">'.e($methodLabel).'</span>';
        $reference = e($payment->reference ?? '—');
        $recordedBy = e($payment->recordedBy->name ?? 'System');

        $destroyUrl = route('admin.payments.invoices.destroy', $payment);
        $csrf = csrf_token();
        $iconTrash = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path></svg>';
        $actions = '<div class="flex items-center justify-end gap-2">'
            .'<form method="POST" action="'.e($destroyUrl).'" class="inline js-invoice-payment-delete-form" data-confirm-title="Delete invoice payment?" data-confirm-text="This will remove all allocations and the related ledger entry. This action cannot be undone.">'
            .'<input type="hidden" name="_token" value="'.e($csrf).'">'
            .'<input type="hidden" name="_method" value="DELETE">'
            .'<button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Delete Payment" aria-label="Delete invoice payment #'.(int) $payment->id.'">'.$iconTrash.'</button>'
            .'</form></div>';

        return [
            $date,
            $invoiceCell,
            $amount,
            $methodBadge,
            $reference,
            $recordedBy,
            $actions,
        ];
    }
}
