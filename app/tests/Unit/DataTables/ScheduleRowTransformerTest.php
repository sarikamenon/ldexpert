<?php

declare(strict_types=1);

use App\DataTables\Transformers\ScheduleRowTransformer;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('renders the date in the given viewer timezone', function () {
    // 2026-06-17 02:00 UTC is 2026-06-16 22:00 in New York (UTC-4 in June),
    // so a New York viewer must see the row dated 2026-06-16.
    $schedule = Schedule::factory()->create([
        'schedule_date' => CarbonImmutable::parse('2026-06-17'),
        'start_time' => '02:00',
    ]);

    $row = ScheduleRowTransformer::transform($schedule, 'America/New_York');

    $expectedDate = CarbonImmutable::parse('2026-06-16')->format(config('display.date'));

    expect($row)->toHaveCount(8)
        ->and($row[0])->toBe($expectedDate);
});

test('falls back to the row display timezone when no viewer timezone is given', function () {
    $schedule = Schedule::factory()->create([
        'schedule_date' => CarbonImmutable::parse('2026-06-17'),
        'start_time' => '02:00',
    ]);

    $expectedTz = $schedule->displayTimezone();
    $expectedDate = $schedule->localStart($expectedTz)->format(config('display.date'));

    $row = ScheduleRowTransformer::transform($schedule);

    expect($row[0])->toBe($expectedDate);
});
