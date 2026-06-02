<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Services\ScheduleMakeupAvailabilityService;
use App\DTOs\Schedule\Makeup\StoreMakeupAvailabilityDTO;
use App\Models\ScheduleMakeupAvailability;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ScheduleMakeupAvailabilityService::class);
});

/**
 * Therapist whose effective timezone is the given zone.
 */
function availabilityServiceTherapist(string $timezone): User
{
    $therapist = User::factory()->therapist()->create(['timezone' => $timezone]);
    TherapistProfile::factory()->create([
        'user_id' => $therapist->id,
        'timezone' => $timezone,
    ]);

    return $therapist;
}

it('persists the window unchanged when the therapist is in UTC', function () {
    $therapist = availabilityServiceTherapist('UTC');

    $window = $this->service->create($therapist, new StoreMakeupAvailabilityDTO(
        date: '2026-07-01',
        startTime: '14:00',
        endTime: '16:00',
        notes: 'Block A',
    ));

    expect($window)->toBeInstanceOf(ScheduleMakeupAvailability::class)
        ->and($window->therapist_id)->toBe($therapist->id)
        ->and($window->availability_date->toDateString())->toBe('2026-07-01')
        ->and($window->start_time->format('H:i'))->toBe('14:00')
        ->and($window->end_time->format('H:i'))->toBe('16:00')
        ->and($window->notes)->toBe('Block A');
});

it('converts user-local time to UTC before storing', function () {
    // 14:00 in New York (EDT, -04:00 on this summer date) is 18:00 UTC.
    $therapist = availabilityServiceTherapist('America/New_York');

    $window = $this->service->create($therapist, new StoreMakeupAvailabilityDTO(
        date: '2026-07-01',
        startTime: '14:00',
        endTime: '16:00',
        notes: null,
    ));

    expect($window->availability_date->toDateString())->toBe('2026-07-01')
        ->and($window->start_time->format('H:i'))->toBe('18:00')
        ->and($window->end_time->format('H:i'))->toBe('20:00');
});

it('shifts the stored date when the local time crosses the UTC day boundary', function () {
    // 22:00 in New York (EDT, -04:00) is 02:00 the next day in UTC.
    $therapist = availabilityServiceTherapist('America/New_York');

    $window = $this->service->create($therapist, new StoreMakeupAvailabilityDTO(
        date: '2026-07-01',
        startTime: '22:00',
        endTime: '23:00',
        notes: null,
    ));

    expect($window->availability_date->toDateString())->toBe('2026-07-02')
        ->and($window->start_time->format('H:i'))->toBe('02:00')
        ->and($window->end_time->format('H:i'))->toBe('03:00');
});

it('soft-deletes a window through the service', function () {
    $therapist = availabilityServiceTherapist('UTC');
    $window = ScheduleMakeupAvailability::factory()->create(['therapist_id' => $therapist->id]);

    $this->service->delete($window);

    $this->assertSoftDeleted('schedule_makeup_availabilities', ['id' => $window->id]);
});
