<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;

final class ScheduleCalendarEventTransformer
{
    /**
     * Transform a Schedule model into a FullCalendar event JSON format.
     *
     * @return array<string, mixed>
     */
    public static function transform(Schedule $schedule): array
    {
        $isPast = $schedule->schedule_date->lt(now()->startOfDay());
        $isBilled = $schedule->billing_status === BillingStatus::BILLED;
        $color = self::eventColor($schedule);

        return [
            'id' => $schedule->id,
            'title' => self::buildTitle($schedule),
            'start' => $schedule->schedule_date->format('Y-m-d').'T'.$schedule->start_time->format('H:i:s'),
            'end' => $schedule->schedule_date->format('Y-m-d').'T'.$schedule->end_time->format('H:i:s'),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'schedule_id' => $schedule->id,
                'therapist_name' => $schedule->therapist?->name,
                'student_name' => $schedule->student?->name,
                'service_name' => $schedule->service?->name,
                'school_name' => self::schoolName($schedule),
                'status' => $schedule->status->value,
                'billing_status' => $schedule->billing_status->value,
                'is_past' => $isPast,
                'is_billed' => $isBilled,
                'is_group' => $schedule->is_group,
            ],
        ];
    }

    private static function schoolName(Schedule $schedule): ?string
    {
        $school = $schedule->school;

        return $school !== null ? ($school->display_name ?? $school->name) : null;
    }

    private static function buildTitle(Schedule $schedule): string
    {
        $student = $schedule->student->name ?? 'N/A';
        $service = $schedule->service->name ?? '';

        return $student.($service ? ' - '.$service : '');
    }

    /**
     * Colour by status + billing. Cancelled overrides everything; otherwise
     * the billing status owns the colour logic via BillingStatus::calendarColor().
     *
     * Hex value required: FullCalendar's JS API accepts only CSS colour strings,
     * not Tailwind utility classes. #9ca3af = foreground/30 (cancelled/muted).
     */
    private static function eventColor(Schedule $schedule): string
    {
        if ($schedule->status === ScheduleStatus::CANCELLED) {
            return '#9ca3af'; // foreground/30 — muted cancelled state
        }

        return $schedule->billing_status->calendarColor($schedule->status);
    }
}
