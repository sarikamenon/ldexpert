<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\Schedule;

final class ScheduleRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings (Date, Time, Therapist, SSA, Service, School, Status, Billing)
     */
    public static function transform(Schedule $schedule): array
    {
        $dateCell = $schedule->schedule_date?->format('Y-m-d') ?? '—';
        $timeCell = $schedule->start_time
            ? ($schedule->start_time->format('H:i').($schedule->end_time ? ' - '.$schedule->end_time->format('H:i') : ''))
            : '—';

        $therapistCell = $schedule->therapist
            ? '<a href="'.e(route('admin.therapists.show', $schedule->therapist)).'" class="text-primary hover:underline">'.e($schedule->therapist->name).'</a>'
            : '—';

        $ssaCell = $schedule->ssa
            ? '<a href="'.e(route('admin.ssas.show', $schedule->ssa)).'" class="text-primary hover:underline">#'.(int) $schedule->ssa->id.'</a>'
            : '—';

        $serviceCell = e($schedule->service?->name ?? '—');
        $schoolCell = e($schedule->school?->display_name ?? '—');

        $statusLabel = $schedule->status?->label() ?? '—';
        $statusClass = $schedule->status?->value === 'completed' ? 'bg-success/10 text-success border border-success/20' : ($schedule->status?->value === 'cancelled' ? 'bg-danger/10 text-danger border border-danger/20' : 'bg-secondary/10 text-secondary border border-secondary/20');
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$statusClass.'">'.e($statusLabel).'</span>';

        $billingLabel = $schedule->billing_status?->label() ?? '—';
        $billingClass = $schedule->billing_status?->value === 'billed' ? 'bg-success/10 text-success border border-success/20' : 'bg-secondary/10 text-secondary border border-secondary/20';
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
