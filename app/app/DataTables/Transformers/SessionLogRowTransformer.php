<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\DataTables\Transformers\Concerns\FormatsSessionLogCells;
use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Support\DateHelper;
use Carbon\Carbon;

final class SessionLogRowTransformer
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
        $sessionDate = $log->localDate($timezone);
        $createdAt = $log->created_at ? Carbon::parse($log->created_at)->setTimezone($timezone) : null;

        $dateTimeDate = $sessionDate->format('M d, Y');
        $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;
        $entryCreated = $createdAt ? $createdAt->format('M d, Y') : null;
        $entryDiff = DateHelper::daysDifferenceBetweenDates($sessionDate, $createdAt);

        $dateLine = e($dateTimeDate);
        if ($duration) {
            $dateLine .= ' <span class="text-foreground/60 font-normal">· '.e($duration).'</span>';
        }

        $dateCell = '<div class="flex flex-col">'
            .'<span class="text-foreground font-medium whitespace-nowrap">'.$dateLine.'</span>';
        if ($entryCreated || $entryDiff) {
            $dateCell .= '<span class="inline-flex flex-wrap items-center gap-1 text-xs text-foreground/50">';
            if ($entryCreated) {
                $dateCell .= 'Entry: '.e($entryCreated);
            }
            if ($entryDiff) {
                $dateCell .= '<span class="inline-flex items-center px-2 py-0.5 leading-none rounded-base font-medium bg-warning/10 text-warning border border-warning/20">'.e($entryDiff).'</span>';
            }
            $dateCell .= '</span>';
        }
        $dateCell .= '</div>';

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

        $therapistName = $log->therapist->name ?? '-';
        $serviceName = $log->service->name ?? null;
        $therapistCell = '<div class="flex flex-col">';
        $therapistCell .= '<span class="font-medium text-foreground">'.e($therapistName).'</span>';
        if ($serviceName) {
            $therapistCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($serviceName).'</span>';
        }
        $therapistCell .= '</div>';

        $amountsCell = self::amountsCell($log);

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

        $viewUrl = route('admin.session-logs.show', $log);
        $buttons = [ActionButtons::view($viewUrl, 'View')];

        if ($log->status === SessionLogStatus::SUBMITTED) {
            $buttons[] = ActionButtons::approve(route('admin.session-logs.approve', $log));
            $buttons[] = ActionButtons::sendBack(route('admin.session-logs.show', $log).'#send-back-form');
        }

        if ($log->status?->canDelete()) {
            $buttons[] = ActionButtons::delete(
                route('admin.session-logs.destroy', $log),
                'Delete',
                'Delete session log?',
                'This will remove the session log and make the session available to log again.',
                ['data-ajax' => 'true'],
            );
        }

        $actionsCell = ActionButtons::wrap(...$buttons);

        return [
            $dateCell,
            $studentSchoolCell,
            $therapistCell,
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
