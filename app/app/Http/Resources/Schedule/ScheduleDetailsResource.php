<?php

declare(strict_types=1);

namespace App\Http\Resources\Schedule;

use App\Constants\UsTimezones;
use App\Domain\Schedule\Sub\Services\CoverageRoleResolver;
use App\Models\Schedule;
use App\Models\ScheduleEmailLog;
use App\Models\ServiceSupportAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape consumed by the schedule details modal. Owns the single
 * "show schedule" response so the controller stays thin and the JS has
 * a stable contract independent of model field churn.
 *
 * Required `additional` keys:
 *  - timezone: string  IANA timezone used to render local start/end times.
 *  - session_log_route: string  Route name for session-log show
 *    (e.g. "therapist.session-logs.show" or "admin.session-logs.show").
 *
 * @property Schedule $resource
 */
final class ScheduleDetailsResource extends JsonResource
{
    public static $wrap = 'schedule';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $schedule = $this->resource;
        $tz = $this->resolveTimezone();

        $localStart = $schedule->localStart($tz);
        $localEnd = $schedule->localEnd($tz);
        $durationMinutes = $schedule->durationMinutes();
        $studentProfile = $schedule->student?->studentProfile;
        $studentTz = $studentProfile->timezone ?? null;

        $viewerId = $this->resolveViewerId($request);
        $coverage = $viewerId !== null
            ? CoverageRoleResolver::for($schedule, $viewerId)
            : ['role' => null, 'badge_label' => null];

        return [
            'id' => $schedule->id,
            'reference' => '#'.str_pad((string) $schedule->id, 2, '0', STR_PAD_LEFT),
            'updated_at_formatted' => $schedule->updated_at?->copy()->setTimezone($tz)->format('M d, Y \a\t '.config('display.time')),
            'schedule_date' => $localStart->format('Y-m-d'),
            'schedule_date_formatted' => $localStart->format('M d, Y'),
            'start_time' => $localStart->format('H:i'),
            'start_time_formatted' => $localStart->format(config('display.time')),
            'end_time' => $localEnd->format('H:i'),
            'end_time_formatted' => $localEnd->format(config('display.time')),
            'duration_minutes' => $durationMinutes,
            'duration_formatted' => self::formatDuration($durationMinutes),
            'timezone' => $tz,
            'timezone_label' => UsTimezones::getTimezoneLabel($tz),
            'status' => $schedule->status->value,
            'billing_status' => $schedule->billing_status->value,
            'notes' => $schedule->notes,
            'location_details' => $schedule->location_details,
            'meeting_link' => $schedule->meetingLink(),
            'meeting_provider' => $schedule->meetingProvider(),
            'is_past' => $localStart->lt(now($tz)->startOfDay()),
            // True for the parent template AND for child occurrences, so the
            // modal can offer "Delete future schedules" on any row of a series.
            'is_recurring' => $schedule->isRecurring() || $schedule->isOccurrence(),
            'service' => [
                'id' => $schedule->service?->id,
                'name' => $schedule->service?->name,
            ],
            'therapist' => [
                'id' => $schedule->therapist?->id,
                'name' => $schedule->therapist?->name,
            ],
            'ssa' => $this->ssaPayload($schedule->ssa),
            'student' => [
                'id' => $schedule->student?->id,
                'name' => $schedule->student?->name,
                'email' => $schedule->student?->email,
                'id_number' => $studentProfile->id_number ?? '-',
                'timezone' => $studentTz ?? '-',
                'timezone_label' => is_string($studentTz) && $studentTz !== ''
                    ? UsTimezones::getTimezoneLabel($studentTz)
                    : '-',
            ],
            'school' => [
                'id' => $schedule->school?->id,
                'name' => $schedule->school->display_name ?? $schedule->school?->name,
            ],
            'parent' => [
                'name' => $studentProfile->parent_guardian_name ?? '-',
                'email' => $studentProfile->parent_guardian_email ?? '-',
                'phone' => $studentProfile->parent_guardian_phone ?? '-',
            ],
            'email_logs' => $schedule->emailLogs
                ->sortByDesc('sent_at')
                ->map(fn (ScheduleEmailLog $log): array => [
                    'sent_at' => $log->sent_at->copy()->setTimezone($tz)->format(config('display.datetime')),
                    'type_label' => $log->type->label(),
                    'type_value' => $log->type->value,
                    'recipient_email' => $log->recipient_email,
                    'sent_by' => $log->sentBy !== null ? $log->sentBy->name : 'System',
                ])
                ->values()
                ->toArray(),
            'coverage' => [
                'role' => $coverage['role'],
                'badge_label' => $coverage['badge_label'],
                'status' => $schedule->sub_request_status?->value,
                'original_therapist' => $schedule->therapist?->name,
                'sub_therapist' => $schedule->subTherapist?->name,
            ],
            'session_log' => $schedule->sessionLog !== null ? [
                'id' => $schedule->sessionLog->id,
                'status' => $schedule->sessionLog->status?->value,
                'status_label' => $schedule->sessionLog->status?->label(),
                'url' => route($this->resolveSessionLogRoute(), $schedule->sessionLog),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ssaPayload(?ServiceSupportAgreement $ssa): ?array
    {
        if ($ssa === null) {
            return null;
        }

        return [
            'id' => $ssa->id,
            'start_date' => $ssa->start_date->format('Y-m-d'),
            'start_date_formatted' => $ssa->start_date->format('M d, Y'),
            'end_date' => $ssa->end_date?->format('Y-m-d'),
            'end_date_formatted' => $ssa->end_date?->format('M d, Y'),
            'date_range_formatted' => $ssa->dateRangeFormatted(),
            'minutes_per_session' => $ssa->minutes_per_session,
            'frequency' => $ssa->frequency?->value,
            'sessions_per_frequency' => $ssa->sessions_per_frequency,
            'summary_line' => $ssa->summaryLine(),
            'hours_line' => $ssa->hoursLine(),
            'status' => $ssa->status->value,
            'tho_minutes' => $ssa->tho_minutes ?? 0,
            'tho_hours' => $ssa->tho_hours,
            'served_minutes' => $ssa->served_minutes ?? 0,
            'served_hours' => $ssa->served_hours,
            'service' => [
                'id' => $ssa->primaryService?->id,
                'name' => $ssa->primaryService?->name,
            ],
        ];
    }

    private function resolveTimezone(): string
    {
        $tz = $this->additional['timezone'] ?? null;

        if (! is_string($tz) || $tz === '') {
            throw new \LogicException(
                'ScheduleDetailsResource requires a timezone via ->additional([\'timezone\' => ...]).'
            );
        }

        return $tz;
    }

    private function resolveSessionLogRoute(): string
    {
        $route = $this->additional['session_log_route'] ?? null;

        if (! is_string($route) || $route === '') {
            throw new \LogicException(
                'ScheduleDetailsResource requires a session_log_route via ->additional([\'session_log_route\' => ...]).'
            );
        }

        return $route;
    }

    private function resolveViewerId(Request $request): ?int
    {
        $viewerId = $this->additional['viewer_id'] ?? null;
        if (is_int($viewerId)) {
            return $viewerId;
        }

        $user = $request->user();

        return $user?->id;
    }

    private static function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$mins}m";
    }
}
