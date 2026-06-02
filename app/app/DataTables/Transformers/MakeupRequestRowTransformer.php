<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\ScheduleMakeupRequest;
use App\Models\User;

final class MakeupRequestRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ScheduleMakeupRequest $row, User $viewer, string $viewerTz): array
    {
        return [
            self::dateCell($row),
            self::studentCell($row),
            self::serviceCell($row),
            self::closureCell($row),
            self::statusCell($row, $viewerTz),
            self::reasonCell($row),
            self::actionsCell($row, $viewer),
        ];
    }

    private static function reasonCell(ScheduleMakeupRequest $row): string
    {
        $reason = $row->reason;

        if ($reason === null || $reason === '') {
            return '<span class="text-foreground/40">—</span>';
        }

        $truncated = mb_strlen($reason) > 40 ? mb_substr($reason, 0, 40).'…' : $reason;

        return '<span class="text-sm text-foreground/80" title="'.e($reason).'">'.e($truncated).'</span>';
    }

    private static function dateCell(ScheduleMakeupRequest $row): string
    {
        $date = $row->event_date->format((string) config('display.date'));

        return '<span class="font-medium text-foreground">'.e($date).'</span>';
    }

    private static function studentCell(ScheduleMakeupRequest $row): string
    {
        $studentName = $row->student->name ?? '—';
        $schoolName = $row->schedule?->school?->display_name;

        $cell = '<div class="flex flex-col">'
            .'<span class="font-medium text-foreground">'.e($studentName).'</span>';
        if ($schoolName) {
            $cell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        $cell .= '</div>';

        return $cell;
    }

    private static function serviceCell(ScheduleMakeupRequest $row): string
    {
        $serviceName = $row->schedule?->service->name ?? '—';

        return '<span class="text-sm text-foreground">'.e($serviceName).'</span>';
    }

    private static function closureCell(ScheduleMakeupRequest $row): string
    {
        $title = $row->calendarEvent->title ?? '—';

        return '<span class="text-sm text-foreground/80">'.e($title).'</span>';
    }

    private static function statusCell(ScheduleMakeupRequest $row, string $viewerTz): string
    {
        $label = $row->status->label();
        $classes = match ($row->status) {
            ScheduleMakeupRequestStatus::PENDING => 'bg-warning/10 text-warning border border-warning/20',
            ScheduleMakeupRequestStatus::SENT => 'bg-accent/10 text-accent border border-accent/20',
            ScheduleMakeupRequestStatus::REQUESTED => 'bg-primary/10 text-primary border border-primary/20',
            ScheduleMakeupRequestStatus::DECLINED => 'bg-danger/10 text-danger border border-danger/20',
            ScheduleMakeupRequestStatus::SCHEDULED => 'bg-success/10 text-success border border-success/20',
            ScheduleMakeupRequestStatus::FAILED => 'bg-danger/10 text-danger border border-danger/20',
            ScheduleMakeupRequestStatus::NOT_REQUIRED => 'bg-muted text-foreground/60 border border-border',
        };

        $badge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$classes.'">'
            .e($label).'</span>';

        if ($row->status === ScheduleMakeupRequestStatus::SCHEDULED && $row->makeup_schedule_id !== null) {
            $schedule = $row->makeupSchedule;
            // schedule_date/start_time are stored in UTC — convert to the
            // viewer's timezone so this matches the schedule calendar.
            $localStart = $schedule?->localStart($viewerTz);
            $date = $localStart?->format((string) config('display.date'));
            $startTime = $localStart?->format('g:i A');
            $label = trim(($date ?? '').($startTime !== null ? ', '.$startTime : ''));

            if ($label !== '') {
                $badge .= '<button type="button" data-schedule-id="'.(int) $row->makeup_schedule_id.'" '
                    .'class="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline focus:outline-none">'
                    .'<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
                    .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'
                    .'</svg>'
                    .'<span>'.e($label).'</span>'
                    .'</button>';

                return '<div class="flex flex-col items-start">'.$badge.'</div>';
            }
        }

        return $badge;
    }

    private static function actionsCell(ScheduleMakeupRequest $row, User $viewer): string
    {
        $eye = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            .'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>'
            .'<circle cx="12" cy="12" r="3"></circle>'
            .'</svg>';

        $declineIcon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"/>'
            .'</svg>';

        $notRequiredIcon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 0a3 3 0 11-6 0 3 3 0 016 0zm12 0a3 3 0 11-6 0 3 3 0 016 0z"/>'
            .'</svg>';

        $calendarIcon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'
            .'</svg>';

        $showUrl = route('therapist.makeup-requests.show', $row);

        $buttons = '<button type="button" '
            .'data-makeup-view-url="'.e($showUrl).'" '
            .'class="inline-flex items-center justify-center w-9 h-9 bg-primary text-white rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" '
            .'title="View make-up request" '
            .'aria-label="View make-up request '.(int) $row->id.'">'
            .$eye
            .'</button>';

        if ($viewer->can('decline', $row)) {
            $declineUrl = route('therapist.makeup-requests.decline', $row);
            $buttons .= '<button type="button" '
                .'data-makeup-decline-url="'.e($declineUrl).'" '
                .'class="inline-flex items-center justify-center w-9 h-9 bg-danger text-white rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" '
                .'title="Decline make-up request" '
                .'aria-label="Decline make-up request '.(int) $row->id.'">'
                .$declineIcon
                .'</button>';
        }

        if ($viewer->can('book', $row)) {
            $bookUrl = route('therapist.makeup-requests.book', $row);
            $buttons .= '<a '
                .'href="'.e($bookUrl).'" '
                .'class="inline-flex items-center justify-center w-9 h-9 bg-success text-white rounded hover:bg-success/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" '
                .'title="Schedule make-up session" '
                .'aria-label="Schedule make-up session for request '.(int) $row->id.'">'
                .$calendarIcon
                .'</a>';
        }

        if ($viewer->can('markNotRequired', $row)) {
            $notRequiredUrl = route('therapist.makeup-requests.mark-not-required', $row);
            $buttons .= '<button type="button" '
                .'data-makeup-not-required-url="'.e($notRequiredUrl).'" '
                .'class="inline-flex items-center justify-center w-9 h-9 bg-transparent text-foreground border border-border rounded hover:bg-muted transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" '
                .'title="Mark as not required" '
                .'aria-label="Mark make-up request '.(int) $row->id.' as not required">'
                .$notRequiredIcon
                .'</button>';
        }

        return '<div class="flex items-center gap-1 justify-end">'.$buttons.'</div>';
    }
}
