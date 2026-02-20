<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SchoolCalendarEventType;

final class SchoolCalendarEventFilterDTO
{
    public function __construct(
        public readonly int $schoolId,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?SchoolCalendarEventType $eventType,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $eventType = null;
        if (! empty($data['event_type'])) {
            $eventType = $data['event_type'] instanceof SchoolCalendarEventType
                ? $data['event_type']
                : SchoolCalendarEventType::tryFrom((string) $data['event_type']);
        }

        return new self(
            schoolId: (int) $data['school_id'],
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            eventType: $eventType,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'event_type' => $this->eventType?->value,
        ];
    }
}
