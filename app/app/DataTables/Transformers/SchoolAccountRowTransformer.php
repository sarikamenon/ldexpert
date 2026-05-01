<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Support\Currency;
use Carbon\CarbonInterface;

final class SchoolAccountRowTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string> 6 cell HTML strings: Date, Student, Description, Debit, Credit, Balance
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
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function dateCell(array $row): string
    {
        $date = $row['date'];
        assert($date instanceof CarbonInterface);

        return '<div class="whitespace-nowrap text-foreground">'.e($date->format('M d, Y')).'</div>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function studentCell(array $row): string
    {
        $name = $row['student_name'] ?? null;
        $id = $row['student_id'] ?? null;

        if (! is_string($name) || $name === '') {
            return '<span class="text-foreground/30">—</span>';
        }

        if (is_int($id) && $id > 0) {
            $url = route('admin.students.show', ['student' => $id]);

            return '<a href="'.e($url).'" class="text-primary hover:underline">'.e($name).'</a>';
        }

        return '<span class="text-foreground">'.e($name).'</span>';
    }

    /**
     * Two-line description with a leading badge for every row.
     * Charge rows whose session has a schedule_id are wrapped in a button
     * that triggers the schedule details modal (handled in JS).
     *
     * @param  array<string, mixed>  $row
     */
    private static function descriptionCell(array $row): string
    {
        $type = is_string($row['type'] ?? null) ? $row['type'] : '';
        $primary = is_string($row['description_primary'] ?? null) ? $row['description_primary'] : '';
        $secondaryText = is_string($row['description_secondary'] ?? null) ? $row['description_secondary'] : null;
        $typeLabel = is_string($row['type_label'] ?? null) ? $row['type_label'] : ucfirst($type);

        // Build the secondary line as escaped text fragments + an optional
        // pre-escaped reference link, so user-controlled text is always escaped
        // regardless of how upstream callers populate description_secondary.
        $secondaryFragments = [];
        if ($secondaryText !== null && $secondaryText !== '') {
            $secondaryFragments[] = e($secondaryText);
        }
        if ($type !== 'charge') {
            $referenceHtml = self::renderReferenceText($row);
            if ($referenceHtml !== null) {
                $secondaryFragments[] = $referenceHtml;
            }
        }

        $badge = self::renderBadge($type, $typeLabel);

        $secondaryHtml = $secondaryFragments !== []
            ? '<div class="mt-0.5 text-xs text-foreground/60">'.implode(' · ', $secondaryFragments).'</div>'
            : '';

        $primaryHtml = '<div class="text-sm text-foreground">'.e($primary).'</div>';

        // Fixed-width badge column keeps the description text aligned across rows
        // regardless of badge label width (e.g. "Charge" vs "Payment Received").
        $body = '<div class="w-32 shrink-0">'.$badge.'</div>'
            .'<div class="flex-1 min-w-0">'.$primaryHtml.$secondaryHtml.'</div>';

        $scheduleId = $row['schedule_id'] ?? null;
        $isCharge = $type === 'charge';

        $wrapper = '<div class="flex items-start gap-2">'.$body.'</div>';

        if ($isCharge && is_int($scheduleId) && $scheduleId > 0) {
            return '<button type="button" data-schedule-id="'.(int) $scheduleId.'" '
                .'class="block w-full text-left hover:bg-background/subtle rounded-base -mx-1 px-1 py-0.5 transition-colors">'
                .$wrapper.'</button>';
        }

        return $wrapper;
    }

    private static function renderBadge(string $type, string $label): string
    {
        $classes = match ($type) {
            'charge' => 'bg-primary/10 text-primary border border-primary/20',
            'payment_received' => 'bg-success/10 text-success border border-success/20',
            'credit_note' => 'bg-success/10 text-success border border-success/20',
            'refund' => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-secondary/10 text-secondary border border-secondary/20',
        };

        return '<span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium whitespace-nowrap '
            .$classes.'">'.e($label).'</span>';
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

        return '<span class="font-semibold text-danger whitespace-nowrap">'
            .e(Currency::formatAbs((float) $amount)).'</span>';
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

        return '<span class="font-semibold text-success whitespace-nowrap">'
            .e(Currency::formatAbs((float) $amount)).'</span>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function balanceCell(array $row): string
    {
        $balance = (float) ($row['balance_after'] ?? 0.0);
        $color = $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : 'text-foreground');
        $suffix = $balance > 0 ? 'DR' : ($balance < 0 ? 'CR' : '');

        $amount = '<span class="font-semibold '.$color.'">'.e(Currency::formatAbs($balance)).'</span>';
        $suffixHtml = $suffix !== ''
            ? ' <span class="text-xs font-medium text-foreground/60">'.$suffix.'</span>'
            : '';

        return '<span class="whitespace-nowrap">'.$amount.$suffixHtml.'</span>';
    }

    /**
     * Build a reference text fragment (HTML — already escaped) for adjustment
     * rows. Used on the secondary line of the description column. Returns null
     * when no reference is available.
     *
     * @param  array<string, mixed>  $row
     */
    private static function renderReferenceText(array $row): ?string
    {
        $reference = $row['reference'] ?? null;
        $referenceType = $row['reference_type'] ?? null;
        $referenceId = $row['reference_id'] ?? null;

        if ($reference === null) {
            return null;
        }

        if ($reference instanceof Invoice && $referenceType === Invoice::class) {
            $url = route('admin.invoices.show', ['invoice' => $reference->id]);
            $label = 'Invoice #'.($reference->invoice_number ?? (string) $reference->id);

            return '<a href="'.e($url).'" class="text-primary hover:underline">'.e($label).'</a>';
        }

        if ($reference instanceof InvoicePayment && $referenceType === InvoicePayment::class) {
            $invoiceId = $reference->invoice?->id;
            $label = 'Payment #'.$reference->id;
            if ($invoiceId !== null) {
                $url = route('admin.invoices.show', ['invoice' => $invoiceId]);

                return '<a href="'.e($url).'" class="text-primary hover:underline">'.e($label).'</a>';
            }

            return e($label);
        }

        $basename = is_string($referenceType) ? class_basename($referenceType) : '';

        return e($basename.' #'.($referenceId ?? ''));
    }
}
