<?php

declare(strict_types=1);

namespace App\Http\Resources\Therapist;

use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ScheduleMakeupRequest $resource
 */
class MakeupRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $makeupRequest = $this->resource;
        $user = $request->user();

        $dateShort = (string) config('display.date');
        $dateLong = (string) config('display.date_long');
        $datetime = (string) config('display.datetime');

        $studentName = $makeupRequest->student?->name;

        return [
            'id' => $makeupRequest->id,
            'status' => $makeupRequest->status->value,
            'status_label' => $this->statusLabel($makeupRequest->status),
            'status_variant' => $this->statusVariant($makeupRequest->status),
            'event_date' => $makeupRequest->event_date->format($dateLong),
            'event_date_short' => $makeupRequest->event_date->format($dateShort),
            'event_date_iso' => $makeupRequest->event_date->toDateString(),
            'reminder_date' => $makeupRequest->reminder_date->format($dateShort),
            'reminder_date_iso' => $makeupRequest->reminder_date->toDateString(),
            'response_date' => $makeupRequest->response_date->format($dateShort),
            'response_date_iso' => $makeupRequest->response_date->toDateString(),
            'deadline_date' => $makeupRequest->deadline_date->format($dateShort),
            'deadline_date_iso' => $makeupRequest->deadline_date->toDateString(),
            'student_name' => $studentName,
            'student_initials' => $this->initials($studentName),
            'school_name' => $makeupRequest->schedule?->school?->display_name,
            'service_name' => $makeupRequest->schedule?->service?->name,
            'closure_title' => $makeupRequest->calendarEvent?->title,
            'reminder_sent_at' => $makeupRequest->reminder_sent_at?->format($datetime),
            'reminder_sent_at_short' => $makeupRequest->reminder_sent_at?->format($dateShort),
            'reminder_sent_at_iso' => $makeupRequest->reminder_sent_at?->toIso8601String(),
            'responded_at' => $makeupRequest->responded_at?->format($datetime),
            'responded_at_short' => $makeupRequest->responded_at?->format($dateShort),
            'responded_at_iso' => $makeupRequest->responded_at?->toIso8601String(),
            'responded_by_type' => $makeupRequest->responded_by_type?->value,
            'responded_by_name' => $makeupRequest->respondedBy?->name,
            'response_source' => $makeupRequest->response_source?->value,
            'decline_kind' => $this->declineKind($makeupRequest),
            'decline_reason' => $makeupRequest->decline_reason,
            'makeup_schedule_id' => $makeupRequest->makeup_schedule_id,
            'makeup_schedule' => $this->makeupSchedulePayload($makeupRequest->makeupSchedule, $dateLong, $dateShort),
            'can_decline' => $user?->can('decline', $makeupRequest) ?? false,
            'can_book' => $user?->can('book', $makeupRequest) ?? false,
            'book_url' => route('therapist.makeup-requests.book', $makeupRequest),
            'decline_url' => route('therapist.makeup-requests.decline', $makeupRequest),
        ];
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

    private function declineKind(ScheduleMakeupRequest $makeupRequest): ?string
    {
        if ($makeupRequest->status !== ScheduleMakeupRequestStatus::DECLINED) {
            return null;
        }

        if ($makeupRequest->response_source === ScheduleMakeupResponseSource::AUTO_DECLINED) {
            return 'auto';
        }

        if ($makeupRequest->responded_by_type === ScheduleMakeupRespondedByType::SYSTEM) {
            return 'auto';
        }

        return 'manual';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function makeupSchedulePayload(?Schedule $schedule, string $dateLong, string $dateShort): ?array
    {
        if ($schedule === null) {
            return null;
        }

        $start = $schedule->start_time;
        $end = $schedule->end_time;
        $duration = $start && $end ? $start->diffInMinutes($end) : null;

        return [
            'id' => $schedule->id,
            'date' => $schedule->schedule_date?->format($dateLong),
            'date_short' => $schedule->schedule_date?->format($dateShort),
            'date_iso' => $schedule->schedule_date?->toDateString(),
            'start_time' => $start?->format('g:i A'),
            'end_time' => $end?->format('g:i A'),
            'duration_minutes' => $duration,
        ];
    }

    private function statusLabel(ScheduleMakeupRequestStatus $status): string
    {
        return match ($status) {
            ScheduleMakeupRequestStatus::PENDING => 'Pending',
            ScheduleMakeupRequestStatus::SENT => 'Awaiting Response',
            ScheduleMakeupRequestStatus::REQUESTED => 'Make-Up Requested',
            ScheduleMakeupRequestStatus::DECLINED => 'Declined',
            ScheduleMakeupRequestStatus::SCHEDULED => 'Scheduled',
            ScheduleMakeupRequestStatus::FAILED => 'Send Failed',
        };
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
        };
    }
}
