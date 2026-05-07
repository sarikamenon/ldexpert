<?php

declare(strict_types=1);

use App\Enums\RecurrenceType;
use App\Models\Schedule;

it('computes durationMinutes from start_time and end_time', function () {
    $schedule = new Schedule([
        'schedule_date' => '2026-05-07',
        'start_time' => '09:00',
        'end_time' => '10:30',
    ]);

    expect($schedule->durationMinutes())->toBe(90);
});

it('returns localStart and localEnd in the requested timezone', function () {
    $schedule = new Schedule([
        'schedule_date' => '2026-05-07',
        'start_time' => '20:00',
        'end_time' => '21:00',
    ]);

    $start = $schedule->localStart('America/Los_Angeles');
    $end = $schedule->localEnd('America/Los_Angeles');

    // 20:00 UTC on 2026-05-07 → 13:00 PDT same day.
    expect($start->format('Y-m-d H:i'))->toBe('2026-05-07 13:00')
        ->and($end->format('Y-m-d H:i'))->toBe('2026-05-07 14:00');
});

it('rolls localEnd to next day only when end_time is strictly before start_time', function () {
    $schedule = new Schedule([
        'schedule_date' => '2026-05-07',
        'start_time' => '23:30',
        'end_time' => '00:30',
    ]);

    $start = $schedule->localStart('UTC');
    $end = $schedule->localEnd('UTC');

    expect($start->format('Y-m-d H:i'))->toBe('2026-05-07 23:30')
        ->and($end->format('Y-m-d H:i'))->toBe('2026-05-08 00:30');
});

it('does not roll localEnd when start_time equals end_time (zero-duration row)', function () {
    $schedule = new Schedule([
        'schedule_date' => '2026-05-07',
        'start_time' => '09:00',
        'end_time' => '09:00',
    ]);

    expect($schedule->localEnd('UTC')->format('Y-m-d'))->toBe('2026-05-07');
});

it('isRecurring returns true only for non-NONE recurrence_type', function () {
    $none = new Schedule(['recurrence_type' => RecurrenceType::NONE]);
    $weekly = new Schedule(['recurrence_type' => RecurrenceType::WEEKLY]);

    expect($none->isRecurring())->toBeFalse()
        ->and($weekly->isRecurring())->toBeTrue();
});

it('isOccurrence returns true only when parent_schedule_id is set', function () {
    $template = new Schedule(['parent_schedule_id' => null]);
    $occurrence = new Schedule(['parent_schedule_id' => 42]);

    expect($template->isOccurrence())->toBeFalse()
        ->and($occurrence->isOccurrence())->toBeTrue();
});
