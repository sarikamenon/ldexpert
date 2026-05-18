<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\SessionLog;

final class ScheduleCalendarEventTransformer
{
    /**
     * Transform a Schedule model into a FullCalendar event JSON format.
     *
     * @return array<string, mixed>
     */
    public static function transform(Schedule $schedule): array
    {
        $tz = $schedule->displayTimezone();
        $localStart = $schedule->localStart($tz);
        $localEnd = $schedule->localEnd($tz);
        $isPast = $localStart->lt(now($tz)->startOfDay());
        $isBilled = $schedule->billing_status === BillingStatus::BILLED;
        $color = self::eventColor($schedule);

        $viewerId = (int) (auth()->id() ?? 0);
        $coverage = self::coverageContext($schedule, $viewerId);

        return [
            'id' => $schedule->id,
            'title' => self::buildTitle($schedule),
            'start' => $localStart->format('Y-m-d\TH:i:s'),
            'end' => $localEnd->format('Y-m-d\TH:i:s'),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'schedule_id' => $schedule->id,
                'therapist_name' => $schedule->therapist?->name,
                'student_name' => $schedule->student?->name,
                'service_name' => $schedule->service?->name,
                'service_color' => $schedule->service?->color,
                'school_name' => self::schoolName($schedule),
                'status' => $schedule->status->value,
                'billing_status' => $schedule->billing_status->value,
                'is_past' => $isPast,
                'is_billed' => $isBilled,
                'is_group' => $schedule->is_group,
                'has_session_log' => $schedule->sessionLog !== null,
                'session_log_status' => $schedule->sessionLog?->status?->value,
                'session_log_outcome' => $schedule->sessionLog?->outcome?->value,
                'sub_request_status' => $schedule->sub_request_status,
                'sub_therapist_name' => $schedule->subTherapist?->name,
                'coverage_role' => $coverage['role'],
                'coverage_badge_label' => $coverage['badge_label'],
            ],
        ];
    }

    /**
     * Resolve the viewer's role for this schedule's sub-coverage state.
     *
     * @return array{role: ?string, badge_label: ?string}
     */
    private static function coverageContext(Schedule $schedule, int $viewerId): array
    {
        $status = $schedule->sub_request_status;
        $therapistId = (int) $schedule->therapist_id;
        $subTherapistId = (int) ($schedule->sub_therapist_id ?? 0);

        if ($status === 'accepted' && $subTherapistId === $viewerId) {
            $originalName = $schedule->therapist?->name;

            return [
                'role' => 'covering',
                'badge_label' => 'Covering for '.($originalName ?? 'therapist'),
            ];
        }

        if ($status === 'accepted' && $therapistId === $viewerId) {
            $subName = $schedule->subTherapist?->name;

            return [
                'role' => 'covered',
                'badge_label' => 'Covered by '.($subName ?? 'sub'),
            ];
        }

        if ($status === 'open' && $therapistId === $viewerId) {
            return [
                'role' => 'open_request',
                'badge_label' => 'Sub requested',
            ];
        }

        return ['role' => null, 'badge_label' => null];
    }

    /**
     * Transform an orphan SessionLog (no schedule attached) into a calendar event.
     *
     * @return array<string, mixed>
     */
    public static function transformOrphanLog(SessionLog $log): array
    {
        $tz = $log->displayTimezone();
        $localStart = $log->localStart($tz);
        $localEnd = $log->localEnd($tz);
        $color = self::orphanLogColor();

        return [
            'id' => 'log-'.$log->id,
            'title' => self::buildOrphanLogTitle($log),
            'start' => $localStart->format('Y-m-d\TH:i:s'),
            'end' => $localEnd->format('Y-m-d\TH:i:s'),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'type' => 'session_log',
                'session_log_id' => $log->id,
                'session_log_status' => $log->status?->value,
                'session_log_outcome' => $log->outcome?->value,
                'has_session_log' => true,
                'is_past' => true,
                'therapist_name' => $log->therapist?->name,
                'student_name' => $log->student?->name,
                'service_name' => $log->service?->name,
            ],
        ];
    }

    private static function buildOrphanLogTitle(SessionLog $log): string
    {
        $student = $log->student->name ?? 'N/A';
        $service = $log->service->name ?? '';

        return $student.($service !== '' ? ' - '.$service : '');
    }

    /**
     * Orphan logs (no schedule attached) use a fixed indigo so therapists can
     * distinguish them at a glance from service-colored schedules. The dashed
     * border + this color together signal "log only, no schedule."
     */
    private static function orphanLogColor(): string
    {
        return '#6b7280'; // gray-500 — reserved for orphan session logs
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
     * Colour priority: cancelled → grey; service color (if set) → billing/status fallback.
     *
     * Hex value required: FullCalendar's JS API accepts only CSS colour strings,
     * not Tailwind utility classes. #9ca3af = foreground/30 (cancelled/muted).
     */
    private static function eventColor(Schedule $schedule): string
    {
        if ($schedule->status === ScheduleStatus::CANCELLED) {
            return '#9ca3af'; // foreground/30 — muted cancelled state
        }

        $serviceColor = $schedule->service?->color;
        if ($serviceColor !== null && $serviceColor !== '') {
            return $serviceColor;
        }

        return $schedule->billing_status->calendarColor($schedule->status);
    }
}
