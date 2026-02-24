<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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

        $period = $invoice->billing_period_start && $invoice->billing_period_end
            ? $invoice->billing_period_start->format('M d').' - '.$invoice->billing_period_end->format('M d, Y')
            : '—';

        $total = '$'.number_format((float) $invoice->total, 2);

        $statusValue = $invoice->status !== null ? (string) $invoice->status : null;
        $statusEnum = $statusValue !== null ? InvoiceStatus::from($statusValue) : null;
        $statusLabel = $statusEnum?->label() ?? '—';
        $badgeClass = match ($statusValue) {
            InvoiceStatus::DRAFT->value => 'bg-secondary/10 text-secondary border border-secondary/20',
            InvoiceStatus::SENT->value => 'bg-primary/10 text-primary border border-primary/20',
            InvoiceStatus::PAID->value => 'bg-success/10 text-success border border-success/20',
            default => 'bg-secondary/10 text-secondary border border-secondary/20',
        };
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $dueDate = $invoice->due_date
            ? $invoice->due_date->format('M d, Y')
            : '—';

        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $iconDownload = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1"></path><path d="M12 12v9"></path><path d="M8 16l4 4 4-4"></path><path d="M12 3v9"></path></svg>';

        $actions = '<div class="flex space-x-1">'
            .'<a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="View Invoice" aria-label="View invoice '.e($invoice->invoice_number).'">'.$iconView.'</a>'
            .'<a href="'.e($downloadUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Download PDF" aria-label="Download invoice '.e($invoice->invoice_number).' as PDF">'.$iconDownload.'</a>'
            .'</div>';

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

