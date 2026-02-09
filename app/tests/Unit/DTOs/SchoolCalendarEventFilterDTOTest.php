<?php

use App\DTOs\SchoolCalendarEventFilterDTO;
use App\Enums\SchoolCalendarEventType;

it('creates filter dto with optional event type', function () {
    $payload = [
        'school_id' => 7,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'event_type' => SchoolCalendarEventType::HOLIDAY->value,
    ];

    $dto = SchoolCalendarEventFilterDTO::fromArray($payload);

    expect($dto->schoolId)->toBe(7)
        ->and($dto->eventType)->toBe(SchoolCalendarEventType::HOLIDAY);

    expect($dto->toArray())->toMatchArray($payload);
});
