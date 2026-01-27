<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SchoolCalendarEventType;

final class CreateSchoolCalendarEventDTO
{
    public function __construct(
        public readonly int $schoolId,
        public readonly string $title,
        public readonly SchoolCalendarEventType $eventType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $notes,
    ) {}

    public static function fromArray(array $data): self
    {
        $eventType = $data['event_type'] instanceof SchoolCalendarEventType
            ? $data['event_type']
            : SchoolCalendarEventType::from($data['event_type']);

        return new self(
            schoolId: (int) $data['school_id'],
            title: $data['title'],
            eventType: $eventType,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'title' => $this->title,
            'event_type' => $this->eventType->value,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'notes' => $this->notes,
        ];
    }
}
