<?php

declare(strict_types=1);

use App\Enums\InvoiceLineType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill schedules.invoice_id from existing advance line items so the
     * generator's notYetInvoiced() filter reflects already-billed schedules.
     * Only ADVANCE_SCHEDULED lines mark a schedule as billed; when a schedule
     * appears on more than one invoice, the latest (by billing_period_end, then
     * id) wins — matching getPreviousAdvanceInvoice()'s ordering.
     */
    public function up(): void
    {
        // Carried across chunk callbacks so the globally-latest invoice wins even
        // when a schedule's rows straddle a chunk boundary (rows arrive latest-first).
        $seen = [];

        DB::table('invoice_line_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_line_items.invoice_id')
            ->whereNotNull('invoice_line_items.schedule_id')
            ->where('invoice_line_items.line_type', InvoiceLineType::ADVANCE_SCHEDULED->value)
            ->whereNull('invoices.deleted_at')
            ->orderByDesc('invoices.billing_period_end')
            ->orderByDesc('invoices.id')
            ->select('invoice_line_items.schedule_id', 'invoice_line_items.invoice_id')
            ->chunk(500, function ($rows) use (&$seen): void {
                foreach ($rows as $row) {
                    if (isset($seen[$row->schedule_id])) {
                        continue;
                    }
                    $seen[$row->schedule_id] = $row->invoice_id;

                    DB::table('schedules')
                        ->where('id', $row->schedule_id)
                        ->update(['invoice_id' => $row->invoice_id]);
                }
            });
    }

    public function down(): void
    {
        // Best-effort reverse: clear only the values this backfill could have set
        // (schedules that carry an ADVANCE_SCHEDULED line for their invoice).
        DB::table('schedules')
            ->whereNotNull('invoice_id')
            ->whereIn('id', function ($query): void {
                $query->select('schedule_id')
                    ->from('invoice_line_items')
                    ->whereNotNull('schedule_id')
                    ->where('line_type', InvoiceLineType::ADVANCE_SCHEDULED->value);
            })
            ->update(['invoice_id' => null]);
    }
};
