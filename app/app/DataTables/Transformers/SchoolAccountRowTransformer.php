<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\CarbonInterface;

final class SchoolAccountRowTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string> 7 cell HTML strings (Date, Student, Description, Debit, Credit, Balance, Reference)
     */
    public static function transform(array $row): array
    {
        return [
            self::dateCell($row),
            self::studentCell($row),
            self::descriptionCell($row),
            self::debitCell($row),
            self::creditCell($row),
            self::balanceCell($row),
            self::referenceCell($row),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function dateCell(array $row): string
    {
        $date = $row['date'] ?? null;
        if (! $date instanceof CarbonInterface) {
            return '<span class="text-foreground/30">—</span>';
        }

        return '<div class="whitespace-nowrap">'.e($date->format('M d, Y')).'</div>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function studentCell(array $row): string
    {
        $name = $row['student_name'] ?? null;
        if (! is_string($name) || $name === '') {
            return '<span class="text-foreground/30">—</span>';
        }

        return '<span class="text-foreground">'.e($name).'</span>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function descriptionCell(array $row): string
    {
        $type = is_string($row['type'] ?? null) ? $row['type'] : '';
        $description = is_string($row['description'] ?? null) ? $row['description'] : '';

        $badge = '';
        if ($type !== 'charge') {
            $badgeClass = match ($type) {
                'payment_received', 'credit_note' => 'bg-success/10 text-success border border-success/20',
                'refund' => 'bg-danger/10 text-danger border border-danger/20',
                default => 'bg-secondary/10 text-secondary border border-secondary/20',
            };
            $label = is_string($row['type_label'] ?? null) ? $row['type_label'] : $type;
            $badge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium whitespace-nowrap mr-2 '
                .$badgeClass.'">'.e($label).'</span>';
        }

        $body = $description !== ''
            ? '<span class="text-foreground/80">'.e($description).'</span>'
            : '<span class="text-foreground/30">—</span>';

        return $badge.$body;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function debitCell(array $row): string
    {
        $amount = $row['debit'] ?? null;
        if ($amount === null) {
            return '<span class="text-foreground/30">—</span>';
        }

        return '<span class="font-semibold text-danger-600">$'.number_format(abs((float) $amount), 2).'</span>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function creditCell(array $row): string
    {
        $amount = $row['credit'] ?? null;
        if ($amount === null) {
            return '<span class="text-foreground/30">—</span>';
        }

        return '<span class="font-semibold text-success-600">$'.number_format(abs((float) $amount), 2).'</span>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function balanceCell(array $row): string
    {
        $balance = (float) ($row['balance_after'] ?? 0.0);
        $class = $balance > 0 ? 'text-danger-600' : 'text-success-600';
        $suffix = $balance > 0 ? ' DR' : ($balance < 0 ? ' CR' : '');

        return '<span class="font-semibold '.$class.'">$'.number_format(abs($balance), 2).e($suffix).'</span>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function referenceCell(array $row): string
    {
        $reference = $row['reference'] ?? null;
        $referenceType = $row['reference_type'] ?? null;
        $referenceId = $row['reference_id'] ?? null;

        if ($reference === null) {
            return '<span class="text-foreground/30">—</span>';
        }

        if ($reference instanceof Invoice && $referenceType === Invoice::class) {
            return '<a href="'.e(route('admin.invoices.show', ['invoice' => $reference->id])).'" class="text-primary hover:underline">Invoice #'
                .e($reference->invoice_number ?? (string) $reference->id).'</a>';
        }

        if ($reference instanceof InvoicePayment && $referenceType === InvoicePayment::class) {
            $invoiceId = $reference->invoice?->id;
            if ($invoiceId !== null) {
                return '<a href="'.e(route('admin.invoices.show', ['invoice' => $invoiceId])).'" class="text-primary hover:underline">Payment #'
                    .e((string) $reference->id).'</a>';
            }

            return 'Payment #'.e((string) ($referenceId ?? $reference->id));
        }

        $basename = is_string($referenceType) ? class_basename($referenceType) : '';

        return e($basename).' #'.e((string) ($referenceId ?? ''));
    }
}
