<?php

use App\DTOs\School\CalendarEvent\UpdateSchoolCalendarEventDTO;
use App\Enums\SchoolCalendarEventType;

it('creates an update school calendar event dto from array', function () {
    $payload = [
        'title' => 'Staff Development',
        'event_type' => SchoolCalendarEventType::NON_HOLIDAY->value,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-12',
        'notes' => 'In-service days.',
    ];

    $dto = UpdateSchoolCalendarEventDTO::fromArray($payload);

    expect($dto->eventType)->toBe(SchoolCalendarEventType::NON_HOLIDAY)
        ->and($dto->title)->toBe('Staff Development');

    expect($dto->toArray())->toMatchArray($payload);
});
