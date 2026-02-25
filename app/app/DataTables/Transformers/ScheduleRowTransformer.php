<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\Schedule;
use App\Enums\ScheduleStatus;
use App\Enums\BillingStatus;
use Carbon\CarbonInterface;

final class ScheduleRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings (Date, Time, Therapist, SSA, Service, School, Status, Billing)
     */
    public static function transform(Schedule $schedule): array
    {
        $rawDate = $schedule->schedule_date;
        $dateCell = $rawDate instanceof CarbonInterface
            ? $rawDate->format('Y-m-d')
            : ($rawDate !== null ? (string) $rawDate : '—');

        $rawStart = $schedule->start_time;
        $rawEnd = $schedule->end_time;
        $startTime = $rawStart instanceof CarbonInterface
            ? $rawStart->format('H:i')
            : ($rawStart !== null ? (string) $rawStart : null);
        $endTime = $rawEnd instanceof CarbonInterface
            ? $rawEnd->format('H:i')
            : ($rawEnd !== null ? (string) $rawEnd : null);
        $timeCell = $startTime !== null
            ? ($endTime !== null ? $startTime.' - '.$endTime : $startTime)
            : '—';

        $therapistCell = $schedule->therapist
            ? '<a href="'.e(route('admin.therapists.show', $schedule->therapist)).'" class="text-primary hover:underline">'.e($schedule->therapist->name).'</a>'
            : '—';

        $ssaCell = $schedule->ssa
            ? '<a href="'.e(route('admin.ssas.show', $schedule->ssa)).'" class="text-primary hover:underline">#'.(int) $schedule->ssa->id.'</a>'
            : '—';

        $serviceCell = e($schedule->service?->name ?? '—');
        $schoolCell = e($schedule->school?->display_name ?? '—');

        $status = $schedule->status;
        if ($status instanceof ScheduleStatus) {
            $statusLabel = $status->label();
            $statusValue = $status->value;
        } else {
            $statusLabel = $status !== null ? (string) $status : '—';
            $statusValue = (string) $status;
        }

        if ($statusValue === ScheduleStatus::COMPLETED->value) {
            $statusClass = 'bg-success/10 text-success border border-success/20';
        } elseif ($statusValue === ScheduleStatus::CANCELLED->value) {
            $statusClass = 'bg-danger/10 text-danger border border-danger/20';
        } else {
            $statusClass = 'bg-secondary/10 text-secondary border border-secondary/20';
        }
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$statusClass.'">'.e($statusLabel).'</span>';

        $billingStatus = $schedule->billing_status;
        if ($billingStatus instanceof BillingStatus) {
            $billingLabel = $billingStatus->label();
            $billingValue = $billingStatus->value;
        } else {
            $billingLabel = $billingStatus !== null ? (string) $billingStatus : '—';
            $billingValue = (string) $billingStatus;
        }

        if ($billingValue === BillingStatus::BILLED->value) {
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
