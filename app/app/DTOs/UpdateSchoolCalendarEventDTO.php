<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SchoolCalendarEventType;

final class UpdateSchoolCalendarEventDTO
{
    public function __construct(
        public readonly string $title,
        public readonly SchoolCalendarEventType $eventType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $reminderDate,
        public readonly string $responseDate,
        public readonly string $deadlineDate,
        public readonly ?string $notes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $eventType = $data['event_type'] instanceof SchoolCalendarEventType
            ? $data['event_type']
            : SchoolCalendarEventType::from($data['event_type']);

        return new self(
            title: $data['title'],
            eventType: $eventType,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            reminderDate: $data['reminder_date'],
            responseDate: $data['response_date'],
            deadlineDate: $data['deadline_date'],
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
            'reminder_date' => $this->reminderDate,
            'response_date' => $this->responseDate,
            'deadline_date' => $this->deadlineDate,
            'notes' => $this->notes,
        ];
    }
}
