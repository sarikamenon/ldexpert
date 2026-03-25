<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;

final class BillingScheduleRunRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(BillingScheduleRun $run, BillingSchedule $schedule): array
    {
        $period = e($run->billing_period_start->format('M d'))
            .' – '
            .e($run->billing_period_end->format('M d, Y'));

        $generated = e($run->generation_date->format('M d, Y'));
        $sessions = e((string) $run->sessions_found);

        if ($schedule->isAdvanceMode()) {
            $adj = e((string) $run->adjustments_count);
            if ((float) $run->adjustment_total != 0) {
                $sign = $run->adjustment_total >= 0 ? '+' : '';
                $adj .= '<span class="text-xs text-foreground/50"> ('.$sign.'$'.e(number_format((float) $run->adjustment_total, 2)).')</span>';
            }

            if ((float) $run->carry_forward_amount > 0) {
                $carry = '$'.e(number_format((float) $run->carry_forward_amount, 2));
            } else {
                $carry = '—';
            }

            $middle = [$adj, $carry];
        } else {
            $middle = [e((string) $run->sessions_from_prior_periods)];
        }

        if ($run->total_amount !== null) {
            $total = '$'.e(number_format((float) $run->total_amount, 2));
        } else {
            $total = '—';
        }

        if ($run->isSuccess()) {
            $status = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-success/10 text-success border border-success/20">Success</span>';
        } elseif ($run->wasSkipped()) {
            $status = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-secondary border border-secondary/20">Skipped</span>';
        } else {
            $status = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-danger/10 text-danger border border-danger/20">Failed</span>';
        }

        if ($run->invoice_id !== null && $run->invoice !== null) {
            $docUrl = route('admin.invoices.show', $run->invoice_id);
            $doc = '<a href="'.e($docUrl).'" class="text-primary hover:underline">'.e($run->invoice->invoice_number).'</a>';
        } elseif ($run->therapist_bill_id !== null && $run->therapistBill !== null) {
            $docUrl = route('admin.billing.therapist-bills.show', $run->therapist_bill_id);
            $doc = '<a href="'.e($docUrl).'" class="text-primary hover:underline">'.e($run->therapistBill->bill_number).'</a>';
        } else {
            $doc = '—';
        }

        return array_merge(
            [
                $period,
                $generated,
                $sessions,
            ],
            $middle,
            [
                $total,
                $status,
                $doc,
            ],
        );
    }
}
