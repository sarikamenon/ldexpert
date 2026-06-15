<?php

use App\DTOs\CreateSchoolCalendarEventDTO;
use App\Enums\SchoolCalendarEventType;

it('creates a school calendar event dto from array', function () {
    $payload = [
        'school_id' => 12,
        'title' => 'Winter Break',
        'event_type' => SchoolCalendarEventType::HOLIDAY->value,
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-06',
        'notes' => 'Campus closed.',
    ];

    $dto = CreateSchoolCalendarEventDTO::fromArray($payload);

    expect($dto->schoolId)->toBe(12)
        ->and($dto->eventType)->toBe(SchoolCalendarEventType::HOLIDAY);

    expect($dto->toArray())->toMatchArray($payload);
});
