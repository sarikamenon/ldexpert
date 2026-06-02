<?php

declare(strict_types=1);

namespace App\DTOs\School\CalendarEvent;

use App\Enums\SchoolCalendarEventType;
use App\Models\SchoolCalendarEvent;

final class SchoolCalendarEventResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $schoolId,
        public readonly string $title,
        public readonly SchoolCalendarEventType $eventType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly bool $requestMakeup,
        public readonly ?string $reminderDate,
        public readonly ?string $responseDate,
        public readonly ?string $notes,
    ) {}

    public static function fromModel(SchoolCalendarEvent $event): self
    {
        return new self(
            id: $event->id,
            schoolId: $event->school_id,
            title: $event->title,
            eventType: $event->event_type,
            startDate: $event->start_date->format('Y-m-d'),
            endDate: $event->end_date->format('Y-m-d'),
            requestMakeup: $event->request_makeup,
            reminderDate: $event->reminder_date?->format('Y-m-d'),
            responseDate: $event->response_date?->format('Y-m-d'),
            notes: $event->notes,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->schoolId,
            'title' => $this->title,
            'event_type' => $this->eventType->value,
            'event_type_label' => $this->eventType->label(),
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'request_makeup' => $this->requestMakeup,
            'reminder_date' => $this->reminderDate,
            'response_date' => $this->responseDate,
            'notes' => $this->notes,
            'is_holiday' => $this->eventType === SchoolCalendarEventType::HOLIDAY,
        ];
    }
}
