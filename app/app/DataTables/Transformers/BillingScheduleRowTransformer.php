<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\BillingSchedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class BillingScheduleRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(BillingSchedule $schedule): array
    {
        $schedulable = $schedule->schedulable;
        $editUrl = self::resolveEntityBillingEditUrl($schedule, $schedulable);
        $historyUrl = route('admin.billing.schedules.history', $schedule);

        // Column 0: Entity name
        $entityName = '—';
        if ($schedulable !== null) {
            $entityName = (string) ($schedulable->getAttribute('display_name')
                ?? $schedulable->getAttribute('name')
                ?? '—');
        }
        $entityCell = '<span class="font-medium">'.e($entityName).'</span>';

        // Column 1: Type
        $typeLabel = $schedule->schedule_type->label();

        // Column 2: Mode
        $modeLabel = $schedule->billing_mode->label();
        $modeBadge = $schedule->isAdvanceMode()
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-warning/10 text-warning border border-warning/20">'.e($modeLabel).'</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-secondary border border-secondary/20">'.e($modeLabel).'</span>';

        // Column 3: Frequency
        $frequencyLabel = $schedule->frequency->label();

        // Column 4: Next Run
        $nextRun = $schedule->next_run_at
            ? $schedule->next_run_at->format('M d, Y')
            : '—';

        // Column 5: Last Run
        $lastRun = $schedule->last_run_at
            ? $schedule->last_run_at->format('M d, Y')
            : 'Never';

        $latestRun = $schedule->latestRun;
        if ($latestRun !== null) {
            $runStatus = $latestRun->isSuccess() ? ' ✓' : ($latestRun->isFailed() ? ' ✗' : '');
            $lastRun .= $runStatus;
        }

        // Column 6: Status
        $statusBadge = $schedule->is_active
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-success/10 text-success border border-success/20">Active</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-secondary border border-secondary/20">Inactive</span>';

        // Column 7: Actions
        $toggleUrl = route('admin.billing.schedules.toggle', $schedule);
        $runUrl = route('admin.billing.schedules.run', $schedule);

        $toggleLabel = $schedule->is_active ? 'Deactivate' : 'Activate';

        $actions = ActionButtons::wrap(
            ActionButtons::edit($editUrl, 'Edit billing'),
            ActionButtons::history($historyUrl, 'Run History'),
        );

        return [
            $entityCell,
            e($typeLabel),
            $modeBadge,
            e($frequencyLabel),
            e($nextRun),
            e($lastRun),
            $statusBadge,
            $actions,
        ];
    }

    private static function resolveEntityBillingEditUrl(BillingSchedule $schedule, ?Model $schedulable): string
    {
        if ($schedulable instanceof School && $schedule->isForSchool()) {
            return route('admin.schools.show', ['school' => $schedulable, 'tab' => 'billing']);
        }

        if ($schedulable instanceof User && $schedule->isForTherapist()) {
            return route('admin.therapists.show', ['therapist' => $schedulable, 'tab' => 'billing']);
        }

        return route('admin.billing.schedules.edit', $schedule);
    }
}
