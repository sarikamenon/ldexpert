<?php

declare(strict_types=1);

namespace App\DTOs\School\CalendarEvent;

use App\Enums\SchoolCalendarEventType;

final class UpdateSchoolCalendarEventDTO
{
    public function __construct(
        public readonly string $title,
        public readonly SchoolCalendarEventType $eventType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly bool $requestMakeup,
        public readonly ?string $reminderDate,
        public readonly ?string $responseDate,
        public readonly ?string $notes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $eventType = $data['event_type'] instanceof SchoolCalendarEventType
            ? $data['event_type']
            : SchoolCalendarEventType::from($data['event_type']);

        $requestMakeup = (bool) ($data['request_makeup'] ?? false);

        return new self(
            title: $data['title'],
            eventType: $eventType,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            requestMakeup: $requestMakeup,
            reminderDate: $requestMakeup ? ($data['reminder_date'] ?? null) : null,
            responseDate: $requestMakeup ? ($data['response_date'] ?? null) : null,
            notes: $data['notes'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'event_type' => $this->eventType->value,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'request_makeup' => $this->requestMakeup,
            'reminder_date' => $this->reminderDate,
            'response_date' => $this->responseDate,
            'notes' => $this->notes,
        ];
    }
}
