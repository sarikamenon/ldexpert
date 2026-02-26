<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\InvoicePayment;

final class InvoicePaymentRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(InvoicePayment $payment): array
    {
        $date = $payment->paid_at->format('M d, Y');

        $invoiceCell = '—';
        if ($payment->invoice) {
            $showUrl = route('admin.invoices.show', $payment->invoice);
            $schoolName = $payment->school->name ?? $payment->invoice->school_name ?? '—';
            $invoiceCell = '<a href="'.e($showUrl).'" class="text-primary hover:underline">'.e($payment->invoice->invoice_number).'</a>'
                .' <span class="text-foreground/60">— '.e($schoolName).'</span>';
        }

        $amount = '$'.number_format((float) $payment->amount, 2);
        $methodLabel = $payment->method->label();
        $methodBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">'.e($methodLabel).'</span>';
        $reference = e($payment->reference ?? '—');
        $recordedBy = e($payment->recordedBy->name ?? 'System');

        $actions = ActionButtons::wrap(
            ActionButtons::delete(
                route('admin.payments.invoices.destroy', $payment),
                'Delete Payment',
                'Delete invoice payment?',
                'This will remove all allocations and the related ledger entry. This action cannot be undone.',
            ),
        );

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
