<?php

declare(strict_types=1);

namespace App\DTOs\Schedule\Makeup;

use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\Schedule;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonImmutable;

/**
 * Input transport for creating a single pending make-up reminder row.
 *
 * Built by the generator from a (closure event, schedule, event date) triple,
 * then handed to the repository which maps it onto the persisted model.
 */
final class CreateMakeupRequestDTO
{
    public function __construct(
        public readonly int $schoolCalendarEventId,
        public readonly int $scheduleId,
        public readonly int $studentId,
        public readonly int $therapistId,
        public readonly CarbonImmutable $eventDate,
        public readonly CarbonImmutable $reminderDate,
        public readonly CarbonImmutable $responseDate,
        public readonly string $batchNumber,
        public readonly string $responseToken,
        public readonly ScheduleMakeupRequestStatus $status = ScheduleMakeupRequestStatus::PENDING,
    ) {}

    public static function fromGeneration(
        SchoolCalendarEvent $event,
        Schedule $schedule,
        CarbonImmutable $eventDate,
        CarbonImmutable $reminderDate,
        CarbonImmutable $responseDate,
        string $batchNumber,
        string $responseToken,
    ): self {
        return new self(
            schoolCalendarEventId: $event->id,
            scheduleId: $schedule->id,
            studentId: $schedule->student_id,
            therapistId: $schedule->sub_therapist_id ?? $schedule->therapist_id,
            eventDate: $eventDate,
            reminderDate: $reminderDate,
            responseDate: $responseDate,
            batchNumber: $batchNumber,
            responseToken: $responseToken,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'school_calendar_event_id' => $this->schoolCalendarEventId,
            'schedule_id' => $this->scheduleId,
            'student_id' => $this->studentId,
            'therapist_id' => $this->therapistId,
            'event_date' => $this->eventDate->toDateString(),
            'reminder_date' => $this->reminderDate->toDateString(),
            'response_date' => $this->responseDate->toDateString(),
            'status' => $this->status->value,
            'batch_number' => $this->batchNumber,
            'response_token' => $this->responseToken,
        ];
    }
}
