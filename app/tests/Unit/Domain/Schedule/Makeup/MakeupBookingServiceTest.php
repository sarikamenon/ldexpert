<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Services\MakeupBookingService;
use App\Domain\Schedule\Makeup\Services\MakeupSlotConflictException;
use App\DTOs\Schedule\Makeup\MakeupSlotPickDTO;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\ScheduleMakeupRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a Path-1 bookable scenario: a therapist with a 14:00–16:00 UTC window on the
 * event date, a 60-min missed session at 09:00 (outside the window), and a SENT request.
 *
 * @return array{0: ScheduleMakeupRequest, 1: Schedule, 2: User, 3: User, 4: string} [row, schedule, therapist, student, eventDate]
 */
function bookingFixture(): array
{
    $eventDate = now()->addDays(7)->toDateString();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    ScheduleMakeupAvailability::factory()->create([
        'therapist_id' => $therapist->id,
        'availability_date' => $eventDate,
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_date' => $eventDate,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'event_date' => $eventDate,
        'responded_at' => null,
    ]);

    return [$row, $schedule, $therapist, $student, $eventDate];
}

function pickAt(int $requestId, string $startUtc, int $durationMinutes = 60): MakeupSlotPickDTO
{
    $start = CarbonImmutable::parse($startUtc, 'UTC');

    return new MakeupSlotPickDTO(
        makeupRequestId: $requestId,
        startUtc: $start,
        endUtc: $start->addMinutes($durationMinutes),
    );
}

it('books a valid slot in place and links the makeup schedule', function () {
    [$row, $schedule, , , $eventDate] = bookingFixture();
    $actor = User::factory()->create();
    $service = app(MakeupBookingService::class);

    $updated = $service->bookSlot($row, pickAt($row->id, $eventDate.' 14:00:00'), $actor->id);

    expect($updated->status)->toBe(ScheduleMakeupRequestStatus::SCHEDULED)
        ->and($updated->makeup_schedule_id)->toBe($schedule->id);

    $schedule->refresh();
    expect($schedule->start_time->format('H:i'))->toBe('14:00')
        ->and($schedule->updated_by)->toBe($actor->id);
});

it('rejects a slot outside any availability window', function () {
    [$row, , , , $eventDate] = bookingFixture();
    $service = app(MakeupBookingService::class);

    // 20:00 is outside the 14:00–16:00 window, no collision — must still be rejected.
    $service->bookSlot($row, pickAt($row->id, $eventDate.' 20:00:00'), 99);
})->throws(MakeupSlotConflictException::class);

it('rejects a slot that does not fit before the window ends', function () {
    [$row, , , , $eventDate] = bookingFixture();
    $service = app(MakeupBookingService::class);

    // 15:30 + 60 min = 16:30 > 16:00 window end — not a valid start.
    $service->bookSlot($row, pickAt($row->id, $eventDate.' 15:30:00'), 99);
})->throws(MakeupSlotConflictException::class);

it('rejects a slot that collides with another therapist session', function () {
    [$row, , $therapist, , $eventDate] = bookingFixture();
    $service = app(MakeupBookingService::class);

    // Another session occupies 14:00–15:00 → subtracted from the window, so 14:00 is gone.
    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => User::factory()->student()->create()->id,
        'schedule_date' => $eventDate,
        'start_time' => '14:00',
        'end_time' => '15:00',
    ]);

    $service->bookSlot($row, pickAt($row->id, $eventDate.' 14:00:00'), 99);
})->throws(MakeupSlotConflictException::class);

it('throws when the request is not in a bookable state', function () {
    [$row] = bookingFixture();
    $row->update(['status' => ScheduleMakeupRequestStatus::DECLINED->value]);
    $service = app(MakeupBookingService::class);

    $service->bookSlot($row->fresh(), pickAt($row->id, now()->addDays(7)->toDateString().' 14:00:00'), 99);
})->throws(InvalidArgumentException::class);

it('availableStartTimes returns 15-min aligned starts that fit the duration', function () {
    [$row, , , , $eventDate] = bookingFixture();
    $service = app(MakeupBookingService::class);

    $starts = collect($service->availableStartTimes($row))
        ->map(fn (CarbonImmutable $s): string => $s->format('H:i'))
        ->all();

    // 60-min session in a 14:00–16:00 window → 14:00 … 15:00 inclusive.
    expect($starts)->toBe(['14:00', '14:15', '14:30', '14:45', '15:00']);
});

it('availableStartTimes returns empty when the therapist has no windows', function () {
    [$row] = bookingFixture();
    ScheduleMakeupAvailability::query()->delete();
    $service = app(MakeupBookingService::class);

    expect($service->availableStartTimes($row->fresh()))->toBe([]);
});
