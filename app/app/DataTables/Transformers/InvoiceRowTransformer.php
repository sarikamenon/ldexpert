<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;

final class InvoiceRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(Invoice $invoice): array
    {
        $showUrl = route('admin.invoices.show', $invoice);
        $downloadUrl = route('admin.invoices.download', $invoice);

        $invoiceNumberButton = '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors" title="View Invoice" aria-label="View invoice '.e($invoice->invoice_number).'">'.e($invoice->invoice_number).'</a>';

        $schoolName = $invoice->school_display_name ?? '—';
        $schoolCell = '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($schoolName).'</a>';

        $period = $invoice->billing_period_start->format('M d').' - '.$invoice->billing_period_end->format('M d, Y');

        $total = '$'.number_format((float) $invoice->total, 2);

        $statusLabel = $invoice->status->label();
        $badgeClass = match ($invoice->status) {
            InvoiceStatus::DRAFT => 'bg-secondary/10 text-secondary border border-secondary/20',
            InvoiceStatus::SENT => 'bg-primary/10 text-primary border border-primary/20',
            InvoiceStatus::PAID => 'bg-success/10 text-success border border-success/20',
        };
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $dueDate = $invoice->due_date
            ? $invoice->due_date->format('M d, Y')
            : '—';

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Invoice'),
            ActionButtons::download($downloadUrl, 'Download PDF'),
        );

        return [
            $invoiceNumberButton,
            $schoolCell,
            e($period),
            $total,
            $statusBadge,
            e($dueDate),
            $actions,
        ];
    }
}
