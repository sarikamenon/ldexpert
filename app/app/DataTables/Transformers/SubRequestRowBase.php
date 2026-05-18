<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ScheduleSubRequest;

/**
 * Shared cell renderers for the two sub-request DataTable views
 * (invited-tab and my-requests-tab).
 *
 * Viewer-derived state (timezone) is passed in by the caller — keep these
 * methods pure so they don't reach into request scope per row.
 */
abstract class SubRequestRowBase
{
    protected static function dateTimeCell(ScheduleSubRequest $subRequest, string $viewerTz): string
    {
        $schedule = $subRequest->schedule;
        $localStart = $schedule?->localStart($viewerTz);

        $date = $localStart ? $localStart->format('M d, Y') : '—';
        $startTime = $localStart ? $localStart->format(config('display.time')) : '—';

        return '<div class="flex flex-col space-y-1">'
            .'<span class="text-foreground font-medium">'.e($date).'</span>'
            .'<span class="text-sm text-foreground/70">'.e($startTime).'</span>'
            .'</div>';
    }

    protected static function studentCell(ScheduleSubRequest $subRequest): string
    {
        $schedule = $subRequest->schedule;
        $studentName = $schedule !== null ? ($schedule->student?->name ?? '—') : '—'; // @phpstan-ignore nullsafe.neverNull
        $schoolName = $schedule !== null ? $schedule->school?->display_name : null;

        $cell = '<div class="flex flex-col">'
            .'<span class="font-medium text-foreground">'.e($studentName).'</span>';
        if ($schoolName) {
            $cell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        $cell .= '</div>';

        return $cell;
    }

    protected static function serviceCell(ScheduleSubRequest $subRequest): string
    {
        $schedule = $subRequest->schedule;
        $serviceName = $schedule !== null ? ($schedule->service?->name ?? '—') : '—'; // @phpstan-ignore nullsafe.neverNull

        return '<span class="text-sm text-foreground">'.e($serviceName).'</span>';
    }

    protected static function reasonCell(ScheduleSubRequest $subRequest): string
    {
        $reason = $subRequest->reason;

        return $reason
            ? '<span class="text-sm text-foreground/80 break-words max-w-xs">'.e($reason).'</span>'
            : '<span class="text-foreground/40">—</span>';
    }
}
