<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\BillingSchedule;

final class BillingScheduleRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(BillingSchedule $schedule): array
    {
        $editUrl = route('admin.billing.schedules.edit', $schedule);
        $historyUrl = route('admin.billing.schedules.history', $schedule);

        // Column 0: Entity name
        $entityName = '—';
        $schedulable = $schedule->schedulable;
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
            ActionButtons::edit($editUrl, 'Edit Schedule'),
            '<a href="'.e($historyUrl).'" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-foreground/60 hover:text-foreground hover:bg-muted transition-colors focus:outline-none focus:ring-2 focus:ring-ring" title="Run History" aria-label="Run History"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></a>',
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
}
