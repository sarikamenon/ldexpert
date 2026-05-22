<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Presenters;

use App\Constants\UsTimezones;
use App\Domain\Time\UserTimezoneService;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Formats parent-facing labels and therapist-facing detail payloads for
 * make-up requests. The therapist `_detail` Blade view and the reminder
 * email both render via this presenter so date/timezone formatting stays
 * in one place.
 */
final class MakeupRequestPresenter
{
    public function __construct(
        private readonly UserTimezoneService $timezoneService,
    ) {}

    /**
     * Render the missed session as "Jun 04, 2026, 3:00 PM–3:30 PM (EST)"
     * in the student's timezone. Falls back to event_date alone when the
     * source schedule is no longer linked.
     */
    public function sessionLabel(ScheduleMakeupRequest $request): string
    {
        $dateFormat = (string) config('display.date');
        $timeFormat = (string) config('display.time');
        $tz = $this->timezoneService->resolveTimezone($request->student);
        $schedule = $request->schedule;

        if ($schedule === null) {
            return $request->event_date->format($dateFormat);
        }

        $start = $schedule->localStart($tz);
        $end = $schedule->localEnd($tz);

        return sprintf(
            '%s, %s–%s (%s)',
            $start->format($dateFormat),
            $start->format($timeFormat),
            $end->format($timeFormat),
            $this->timezoneAbbreviation($tz),
        );
    }

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     * @return array<int, string>
     */
    public function sessionLabels(Collection $batch): array
    {
        return $batch
            ->map(fn (ScheduleMakeupRequest $row): string => $this->sessionLabel($row))
            ->values()
            ->all();
    }

    /**
     * Build the fully-shaped payload for the therapist `_detail` Blade view.
     * All date formatting, status banners, and policy checks are resolved
     * here so the view contains no `@php` data-shaping.
     *
     * @return array<string, mixed>
     */
    public function detail(ScheduleMakeupRequest $request, ?User $viewer): array
    {
        $dateShort = (string) config('display.date');
        $dateLong = (string) config('display.date_long');
        $datetime = (string) config('display.datetime');
        $viewerTz = $this->timezoneService->resolveTimezone($viewer);
        $today = CarbonImmutable::today($viewerTz);

        $studentName = $request->student?->name;
        $isAutoDecline = $this->declineKind($request) === 'auto';
        $respondedShort = $request->responded_at?->format($dateShort);
        $reminderSentShort = $request->reminder_sent_at?->format($dateShort);

        return [
            'id' => $request->id,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_variant' => $this->statusVariant($request->status),
            'event_date' => $request->event_date->format($dateLong),
            'reminder_date' => $request->reminder_date->format($dateShort),
            'reminder_date_relative' => $this->relativeFromDate($request->reminder_date, $today, $viewerTz),
            'response_date' => $request->response_date->format($dateShort),
            'response_date_relative' => $this->relativeFromDate($request->response_date, $today, $viewerTz),
            'student_name' => $studentName ?? 'Unknown',
            'student_initials' => $this->initials($studentName),
            'school_name' => $request->schedule?->school?->display_name,
            'service_name' => $request->schedule?->service?->name,
            'service_meta' => collect([$request->schedule?->school?->display_name, $request->schedule?->service?->name])
                ->filter()
                ->join(' · '),
            // @phpstan-ignore nullsafe.neverNull (FK is nullable; model generics don't express that)
            'closure_title' => $request->calendarEvent?->title ?? '—',
            'reminder_sent_at' => $request->reminder_sent_at?->format($datetime),
            'reminder_sent_at_short' => $reminderSentShort,
            'responded_at_short' => $respondedShort,
            'responded_by_name' => $request->respondedBy?->name,
            'decline_kind' => $this->declineKind($request),
            'is_auto_decline' => $isAutoDecline,
            'decline_banner_title' => $isAutoDecline ? 'Auto-declined — no response from parent' : 'Manually declined',
            'decline_banner_sub' => $this->declineBannerSub($request, $isAutoDecline, $respondedShort),
            'reason' => $request->reason,
            'makeup_schedule' => $this->makeupSchedulePayload($request->makeupSchedule, $dateLong, $dateShort),
            'can_decline' => $viewer?->can('decline', $request) ?? false,
            'can_book' => $viewer?->can('book', $request) ?? false,
            'can_mark_not_required' => $viewer?->can('markNotRequired', $request) ?? false,
            'book_url' => route('therapist.makeup-requests.book', $request),
            'decline_url' => route('therapist.makeup-requests.decline', $request),
            'mark_not_required_url' => route('therapist.makeup-requests.mark-not-required', $request),
        ];
    }

    /**
     * Resolve the project's DST-agnostic timezone abbreviation (e.g. "ET",
     * "PT") from `UsTimezones`, extracting the parenthesized code from the
     * display label. Falls back to the IANA zone name when the zone isn't
     * in the project's curated US list.
     */
    private function timezoneAbbreviation(string $timezone): string
    {
        $label = UsTimezones::getTimezoneLabel($timezone);

        if (preg_match('/\(([^)]+)\)\s*$/', $label, $matches) === 1) {
            return $matches[1];
        }

        return $timezone;
    }

    /**
     * Compare two calendar dates in the viewer's timezone to avoid off-by-one
     * errors near UTC midnight. Both dates are normalized to date-strings and
     * re-parsed in the viewer TZ before diffing.
     */
    private function relativeFromDate(CarbonInterface $date, CarbonInterface $today, string $viewerTz): string
    {
        $normalized = Carbon::parse($date->toDateString(), $viewerTz)->startOfDay();
        $todayNormalized = Carbon::parse($today->toDateString(), $viewerTz)->startOfDay();
        $days = (int) $todayNormalized->diffInDays($normalized, false);

        return match (true) {
            $days === 0 => 'today',
            $days === 1 => 'tomorrow',
            $days === -1 => 'yesterday',
            $days > 1 => "in {$days} days",
            default => abs($days).' days ago',
        };
    }

    private function initials(?string $name): string
    {
        if ($name === null || $name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 0) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = mb_substr($parts[count($parts) - 1], 0, 1);

        return mb_strtoupper($first.$last);
    }

    private function declineKind(ScheduleMakeupRequest $request): ?string
    {
        if ($request->status !== ScheduleMakeupRequestStatus::DECLINED) {
            return null;
        }

        if ($request->response_source === ScheduleMakeupResponseSource::AUTO_DECLINED) {
            return 'auto';
        }

        if ($request->responded_by_type === ScheduleMakeupRespondedByType::SYSTEM) {
            return 'auto';
        }

        return 'manual';
    }

    private function declineBannerSub(ScheduleMakeupRequest $request, bool $isAutoDecline, ?string $respondedShort): string
    {
        if ($isAutoDecline) {
            return $respondedShort !== null ? "Declined automatically on {$respondedShort}" : 'Declined automatically';
        }

        $by = $request->respondedBy?->name !== null ? ' by '.$request->respondedBy->name : '';

        return $respondedShort !== null ? "Declined on {$respondedShort}{$by}" : 'Declined';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function makeupSchedulePayload(?Schedule $schedule, string $dateLong, string $dateShort): ?array
    {
        if ($schedule === null) {
            return null;
        }

        $timeFormat = (string) config('display.time');
        $start = $schedule->start_time;
        $end = $schedule->end_time;
        $duration = (int) $start->diffInMinutes($end);
        $timeRange = $start->format($timeFormat).' – '.$end->format($timeFormat);
        $meta = collect([$timeRange, $duration.' minutes'])->filter()->join(' · ');

        return [
            'id' => $schedule->id,
            'date' => $schedule->schedule_date->format($dateLong),
            'date_short' => $schedule->schedule_date->format($dateShort),
            'start_time' => $start->format($timeFormat),
            'end_time' => $end->format($timeFormat),
            'duration_minutes' => $duration,
            'meta' => $meta,
        ];
    }

    private function statusVariant(ScheduleMakeupRequestStatus $status): string
    {
        return match ($status) {
            ScheduleMakeupRequestStatus::PENDING => 'warning',
            ScheduleMakeupRequestStatus::SENT => 'info',
            ScheduleMakeupRequestStatus::REQUESTED => 'primary',
            ScheduleMakeupRequestStatus::DECLINED => 'danger',
            ScheduleMakeupRequestStatus::SCHEDULED => 'success',
            ScheduleMakeupRequestStatus::FAILED => 'danger',
            ScheduleMakeupRequestStatus::NOT_REQUIRED => 'secondary',
        };
    }
}
