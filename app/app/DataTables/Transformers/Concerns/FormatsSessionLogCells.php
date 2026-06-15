<?php

declare(strict_types=1);

namespace App\DataTables\Transformers\Concerns;

use App\Models\SessionLog;

/**
 * Cell builders shared by the admin and therapist session-log row
 * transformers. The notes markup must stay in sync with the clamp CSS in
 * resources/css/common/datatables.css (`.notes-cell` / `.notes-text`) and the
 * toggle JS in resources/js/common/session-log-notes.js.
 */
trait FormatsSessionLogCells
{
    private static function notesCell(?string $notes): string
    {
        $notes = $notes !== null ? trim($notes) : '';
        if ($notes === '') {
            return '<span class="text-foreground/40">-</span>';
        }

        return '<div class="notes-cell" data-notes-cell>'
            .'<div class="notes-text text-sm text-foreground/80" data-notes-text>'.e($notes).'</div>'
            .'<button type="button" class="notes-toggle hidden text-xs text-primary mt-1 hover:underline focus-visible:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-base" data-notes-toggle aria-expanded="false">Read more</button>'
            .'</div>';
    }

    private static function amountsCell(SessionLog $log, bool $showSchoolAmount = true): string
    {
        $html = '<div class="flex flex-col space-y-1">';
        if ($showSchoolAmount) {
            $html .= '<span class="text-xs text-foreground/60">School: <span class="text-foreground font-medium">'.e(self::formatCurrency($log->school_invoice_amount)).'</span></span>';
        }
        $html .= '<span class="text-xs text-foreground/60">Therapist: <span class="text-foreground font-medium">'.e(self::formatCurrency($log->therapist_billable_amount)).'</span></span>'
            .'</div>';

        return $html;
    }

    private static function formatCurrency(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }
        if (! is_numeric($amount)) {
            return '-';
        }

        return '$'.number_format((float) $amount, 2);
    }
}
