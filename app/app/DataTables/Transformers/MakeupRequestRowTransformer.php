<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\ScheduleMakeupRequest;

final class MakeupRequestRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ScheduleMakeupRequest $row): array
    {
        return [
            self::dateCell($row),
            self::studentCell($row),
            self::serviceCell($row),
            self::closureCell($row),
            self::statusCell($row),
            self::actionsCell($row),
        ];
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

    private static function statusCell(ScheduleMakeupRequest $row): string
    {
        [$label, $classes] = match ($row->status) {
            ScheduleMakeupRequestStatus::PENDING => ['Pending', 'bg-warning/10 text-warning border border-warning/20'],
            ScheduleMakeupRequestStatus::SENT => ['Awaiting Response', 'bg-accent/10 text-accent border border-accent/20'],
            ScheduleMakeupRequestStatus::REQUESTED => ['Make-Up Requested', 'bg-primary/10 text-primary border border-primary/20'],
            ScheduleMakeupRequestStatus::DECLINED => ['Declined', 'bg-danger/10 text-danger border border-danger/20'],
            ScheduleMakeupRequestStatus::SCHEDULED => ['Scheduled', 'bg-success/10 text-success border border-success/20'],
            ScheduleMakeupRequestStatus::FAILED => ['Send Failed', 'bg-danger/10 text-danger border border-danger/20'],
        };

        return '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$classes.'">'
            .e($label).'</span>';
    }

    private static function actionsCell(ScheduleMakeupRequest $row): string
    {
        $eye = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            .'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>'
            .'<circle cx="12" cy="12" r="3"></circle>'
            .'</svg>';

        return '<div class="flex items-center gap-1 justify-end">'
            .'<button type="button" '
            .'data-makeup-view="'.(int) $row->id.'" '
            .'class="inline-flex items-center justify-center w-9 h-9 bg-primary text-white rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" '
            .'title="View make-up request" '
            .'aria-label="View make-up request '.(int) $row->id.'">'
            .$eye
            .'</button>'
            .'</div>';
    }
}
