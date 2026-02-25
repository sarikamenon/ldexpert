<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;

final class ScheduleRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings (Date, Time, Therapist, SSA, Service, School, Status, Billing)
     */
    public static function transform(Schedule $schedule): array
    {
        $dateCell = $schedule->schedule_date->format('Y-m-d');

        $startTime = $schedule->start_time->format('H:i');
        $endTime = $schedule->end_time->format('H:i');
        $timeCell = $startTime.' - '.$endTime;

        $therapistCell = $schedule->therapist
            ? '<a href="'.e(route('admin.therapists.show', $schedule->therapist)).'" class="text-primary hover:underline">'.e($schedule->therapist->name).'</a>'
            : '—';

        $ssaCell = $schedule->ssa
            ? '<a href="'.e(route('admin.ssas.show', $schedule->ssa)).'" class="text-primary hover:underline">#'.(int) $schedule->ssa->id.'</a>'
            : '—';

        $serviceCell = e($schedule->service->name ?? '—');
        $schoolCell = e($schedule->school->display_name ?? '—');

        $status = $schedule->status;
        $statusLabel = $status->label();

        if ($status === ScheduleStatus::COMPLETED) {
            $statusClass = 'bg-success/10 text-success border border-success/20';
        } elseif ($status === ScheduleStatus::CANCELLED) {
            $statusClass = 'bg-danger/10 text-danger border border-danger/20';
        } else {
            $statusClass = 'bg-secondary/10 text-secondary border border-secondary/20';
        }
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$statusClass.'">'.e($statusLabel).'</span>';

        $billingStatus = $schedule->billing_status;
        $billingLabel = $billingStatus->label();

        if ($billingStatus === BillingStatus::BILLED) {
            $billingClass = 'bg-success/10 text-success border border-success/20';
        } else {
            $billingClass = 'bg-secondary/10 text-secondary border border-secondary/20';
        }
        $billingCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$billingClass.'">'.e($billingLabel).'</span>';

        return [
            $dateCell,
            $timeCell,
            $therapistCell,
            $ssaCell,
            $serviceCell,
            $schoolCell,
            $statusCell,
            $billingCell,
        ];
    }
}
