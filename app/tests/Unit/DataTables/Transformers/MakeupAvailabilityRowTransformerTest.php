<?php

declare(strict_types=1);

use App\DataTables\Transformers\MakeupAvailabilityRowTransformer;
use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->therapist = User::factory()->therapist()->create();
});

/**
 * A 14:00–17:00 (UTC) window on a fixed date.
 */
function transformerWindow(User $therapist): ScheduleMakeupAvailability
{
    return ScheduleMakeupAvailability::factory()->create([
        'therapist_id' => $therapist->id,
        'availability_date' => '2026-07-01',
        'start_time' => '14:00',
        'end_time' => '17:00',
        'notes' => 'Afternoon block',
    ]);
}

function transformerSchedule(User $therapist, string $start, string $end): Schedule
{
    return Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-07-01',
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

it('renders the window fields and empty booked slots in UTC', function () {
    $window = transformerWindow($this->therapist);

    $row = MakeupAvailabilityRowTransformer::transform($window, 'UTC', new Collection);

    expect($row['id'])->toBe((int) $window->id)
        ->and($row['date'])->toBe('Jul 01, 2026')
        ->and($row['start'])->toBe('2:00 PM')
        ->and($row['end'])->toBe('5:00 PM')
        ->and($row['notes'])->toBe('Afternoon block')
        ->and($row['booked_slots'])->toBe([])
        ->and($row['delete_url'])->toContain((string) $window->id);
});

it('renders booked sub-slots as a formatted time range', function () {
    $window = transformerWindow($this->therapist);
    $booked = transformerSchedule($this->therapist, '15:00', '16:00');

    $row = MakeupAvailabilityRowTransformer::transform($window, 'UTC', collect([$booked]));

    expect($row['booked_slots'])->toBe(['3:00 PM – 4:00 PM']);
});

it('converts window and booked slots into the viewer timezone', function () {
    // New York is UTC-04:00 on this summer date, so 14:00 UTC → 10:00 AM local.
    $window = transformerWindow($this->therapist);
    $booked = transformerSchedule($this->therapist, '15:00', '16:00');

    $row = MakeupAvailabilityRowTransformer::transform($window, 'America/New_York', collect([$booked]));

    expect($row['start'])->toBe('10:00 AM')
        ->and($row['end'])->toBe('1:00 PM')
        ->and($row['booked_slots'])->toBe(['11:00 AM – 12:00 PM']);
});

it('renders multiple booked slots in order', function () {
    $window = transformerWindow($this->therapist);
    $first = transformerSchedule($this->therapist, '14:15', '14:45');
    $second = transformerSchedule($this->therapist, '16:00', '16:30');

    $row = MakeupAvailabilityRowTransformer::transform(
        $window,
        'UTC',
        collect([$first, $second]),
    );

    expect($row['booked_slots'])->toBe(['2:15 PM – 2:45 PM', '4:00 PM – 4:30 PM']);
});
