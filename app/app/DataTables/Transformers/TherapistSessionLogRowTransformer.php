<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\DataTables\Transformers\Concerns\FormatsSessionLogCells;
use App\Models\SessionLog;
use App\Support\DateHelper;
use Carbon\Carbon;

final class TherapistSessionLogRowTransformer
{
    use FormatsSessionLogCells;

    /**
     * $timezone is the logged-in viewer's timezone, resolved once per request
     * via UserTimezoneService::resolveTimezone() — never the row owner's.
     *
     * @return array<int, string>
     */
    public static function transform(SessionLog $log, string $timezone): array
    {
        $localStart = $log->localStart($timezone);
        $localEnd = $log->localEnd($timezone);
        $sessionDate = $localStart;
        $createdAt = $log->created_at ? Carbon::parse($log->created_at)->setTimezone($timezone) : null;

        $startTime = $localStart->format(config('display.time'));
        $endTime = $localEnd->format(config('display.time'));
        $timeRange = "{$startTime} - {$endTime}";
        $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;

        $dateLine = e($sessionDate->format('M d, Y'));
        if ($duration) {
            $dateLine .= ' <span class="text-foreground/60 font-normal">· '.e($duration).'</span>';
        }

        $entryCreated = $createdAt ? $createdAt->format('M d, Y') : null;
        $entryDiff = DateHelper::daysDifferenceBetweenDates($sessionDate, $createdAt);

        $dateTimeCell = '<div class="flex flex-col">'
            .'<span class="text-foreground font-medium whitespace-nowrap">'.$dateLine.'</span>'
            .'<span class="text-foreground text-sm">'.e($timeRange).'</span>';
        if ($entryCreated || $entryDiff) {
            $dateTimeCell .= '<span class="inline-flex flex-wrap items-center gap-1 text-xs text-foreground/50">';
            if ($entryCreated) {
                $dateTimeCell .= 'Entry: '.e($entryCreated);
            }
            if ($entryDiff) {
                $dateTimeCell .= '<span class="inline-flex items-center px-2 py-0.5 leading-none rounded-base font-medium bg-warning/10 text-warning border border-warning/20">'.e($entryDiff).'</span>';
            }
            $dateTimeCell .= '</span>';
        }
        $dateTimeCell .= '</div>';

        $studentName = $log->student->name ?? null;
        $schoolName = $log->school->display_name ?? null;
        $studentSchoolCell = '<div class="flex flex-col">';
        if ($studentName) {
            $studentSchoolCell .= '<span class="font-medium text-foreground">'.e($studentName).'</span>';
        }
        if ($schoolName) {
            $studentSchoolCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        if (! $studentName && ! $schoolName) {
            $studentSchoolCell .= '<span class="text-foreground/40">-</span>';
        }
        $studentSchoolCell .= '</div>';

        $serviceName = $log->service->name ?? null;
        $therapistServiceCell = '<div class="flex flex-col">';
        $therapistServiceCell .= '<span class="font-medium text-foreground">'.e($log->therapist->name ?? '-').'</span>';
        if ($serviceName) {
            $therapistServiceCell .= '<span class="text-sm text-foreground/70 mt-0.5">'.e($serviceName).'</span>';
        }
        $therapistServiceCell .= '</div>';

        $amountsCell = self::amountsCell($log, showSchoolAmount: false);

        $notesCell = self::notesCell($log->notes);

        $statusLabel = self::getStatusLabel($log);
        $statusVariant = match ($statusLabel) {
            'Approved' => 'success',
            'Submitted', 'Sent back' => 'warning',
            'Cancelled' => 'danger',
            default => 'secondary',
        };
        $badgeClass = match ($statusVariant) {
            'success' => 'bg-success/10 text-success border border-success/20',
            'warning' => 'bg-warning/10 text-warning border border-warning/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-secondary/10 text-secondary border border-secondary/20',
        };
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $viewUrl = route('therapist.session-logs.show', $log);
        $buttons = [ActionButtons::view($viewUrl, 'View')];

        if ($log->status?->canEdit()) {
            $buttons[] = ActionButtons::edit(route('therapist.session-logs.edit', $log), 'Edit');
            $buttons[] = ActionButtons::submit(route('therapist.session-logs.submit', $log));
        }

        if ($log->status?->canDelete()) {
            $buttons[] = ActionButtons::delete(
                route('therapist.session-logs.destroy', $log),
                'Delete',
                'Delete session log?',
                'This will remove the session log and make the session available to log again.',
            );
        }

        $actionsCell = ActionButtons::wrap(...$buttons);

        return [
            $dateTimeCell,
            $studentSchoolCell,
            $therapistServiceCell,
            $amountsCell,
            $notesCell,
            $statusCell,
            $actionsCell,
        ];
    }

    private static function getStatusLabel(SessionLog $log): string
    {
        try {
            return $log->status?->label() ?? '-';
        } catch (\Throwable) {
            return '-';
        }
    }
}
