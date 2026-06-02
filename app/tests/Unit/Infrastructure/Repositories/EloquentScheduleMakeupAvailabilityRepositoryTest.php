<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(ScheduleMakeupAvailabilityRepositoryInterface::class);
    $this->therapist = User::factory()->therapist()->create();
});

/**
 * A 14:00–17:00 (UTC) availability window on a fixed date for the test therapist.
 */
function availabilityWindow(User $therapist, string $date = '2026-07-01'): ScheduleMakeupAvailability
{
    return ScheduleMakeupAvailability::factory()->create([
        'therapist_id' => $therapist->id,
        'availability_date' => $date,
        'start_time' => '14:00',
        'end_time' => '17:00',
    ]);
}

/**
 * A schedule owned by the therapist on the given date/time with the given status.
 */
function therapistSchedule(
    User $therapist,
    string $date,
    string $start,
    string $end,
    ScheduleStatus $status = ScheduleStatus::SCHEDULED,
): Schedule {
    return Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'status' => $status,
    ]);
}

// ─── schedulesOverlappingWindow ──────────────────────────────────────────────

it('finds a schedule that sits inside the window', function () {
    $window = availabilityWindow($this->therapist);
    $booked = therapistSchedule($this->therapist, '2026-07-01', '15:00', '16:00');

    $result = $this->repository->schedulesOverlappingWindow($window);

    expect($result->pluck('id')->all())->toBe([$booked->id]);
});

it('finds a schedule that partially overlaps the window edge', function () {
    $window = availabilityWindow($this->therapist);
    // 13:30–14:30 straddles the 14:00 window start.
    $booked = therapistSchedule($this->therapist, '2026-07-01', '13:30', '14:30');

    $result = $this->repository->schedulesOverlappingWindow($window);

    expect($result->pluck('id')->all())->toBe([$booked->id]);
});

it('excludes a schedule that abuts the window without overlapping', function () {
    $window = availabilityWindow($this->therapist);
    // 17:00–18:00 starts exactly at the window end — half-open, so no overlap.
    therapistSchedule($this->therapist, '2026-07-01', '17:00', '18:00');

    expect($this->repository->schedulesOverlappingWindow($window))->toHaveCount(0);
});

it('excludes a schedule on a different day', function () {
    $window = availabilityWindow($this->therapist);
    therapistSchedule($this->therapist, '2026-07-02', '15:00', '16:00');

    expect($this->repository->schedulesOverlappingWindow($window))->toHaveCount(0);
});

it('excludes a cancelled schedule from the booked set', function () {
    $window = availabilityWindow($this->therapist);
    therapistSchedule($this->therapist, '2026-07-01', '15:00', '16:00', ScheduleStatus::CANCELLED);

    expect($this->repository->schedulesOverlappingWindow($window))->toHaveCount(0);
});

it('includes a completed schedule in the booked set', function () {
    $window = availabilityWindow($this->therapist);
    $booked = therapistSchedule($this->therapist, '2026-07-01', '15:00', '16:00', ScheduleStatus::COMPLETED);

    expect($this->repository->schedulesOverlappingWindow($window)->pluck('id')->all())
        ->toBe([$booked->id]);
});

it('excludes another therapist schedule from the window', function () {
    $window = availabilityWindow($this->therapist);
    $other = User::factory()->therapist()->create();
    therapistSchedule($other, '2026-07-01', '15:00', '16:00');

    expect($this->repository->schedulesOverlappingWindow($window))->toHaveCount(0);
});

it('orders overlapping schedules by their start instant', function () {
    $window = availabilityWindow($this->therapist);
    $late = therapistSchedule($this->therapist, '2026-07-01', '16:00', '16:30');
    $early = therapistSchedule($this->therapist, '2026-07-01', '14:15', '14:45');

    $result = $this->repository->schedulesOverlappingWindow($window);

    expect($result->pluck('id')->all())->toBe([$early->id, $late->id]);
});

// ─── busySchedulesForWindows ─────────────────────────────────────────────────

it('returns an empty collection when no windows are given', function () {
    therapistSchedule($this->therapist, '2026-07-01', '15:00', '16:00');

    $result = $this->repository->busySchedulesForWindows($this->therapist, new Collection);

    expect($result)->toHaveCount(0);
});

it('collects busy schedules across multiple windows', function () {
    $windowA = availabilityWindow($this->therapist, '2026-07-01');
    $windowB = availabilityWindow($this->therapist, '2026-07-02');

    $inA = therapistSchedule($this->therapist, '2026-07-01', '15:00', '16:00');
    $inB = therapistSchedule($this->therapist, '2026-07-02', '14:30', '15:30');
    // Outside both windows — must be ignored.
    therapistSchedule($this->therapist, '2026-07-03', '15:00', '16:00');

    $result = $this->repository->busySchedulesForWindows(
        $this->therapist,
        collect([$windowA, $windowB]),
    );

    expect($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$inA->id, $inB->id])->sort()->values()->all());
});

it('excludes cancelled schedules from busySchedulesForWindows', function () {
    $window = availabilityWindow($this->therapist);
    therapistSchedule($this->therapist, '2026-07-01', '15:00', '16:00', ScheduleStatus::CANCELLED);

    $result = $this->repository->busySchedulesForWindows($this->therapist, collect([$window]));

    expect($result)->toHaveCount(0);
});

// ─── therapistHasAvailabilityFromDate ────────────────────────────────────────

it('reports availability on or after the given date', function () {
    availabilityWindow($this->therapist, '2026-07-10');

    expect($this->repository->therapistHasAvailabilityFromDate($this->therapist, '2026-07-01'))->toBeTrue()
        ->and($this->repository->therapistHasAvailabilityFromDate($this->therapist, '2026-07-10'))->toBeTrue()
        ->and($this->repository->therapistHasAvailabilityFromDate($this->therapist, '2026-07-11'))->toBeFalse();
});
