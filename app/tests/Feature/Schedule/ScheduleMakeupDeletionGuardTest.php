<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Exceptions\CannotDeleteScheduleWithMakeupException;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a schedule that carries a make-up request in the given status.
 *
 * @return array{0: Schedule, 1: ScheduleMakeupRequest}
 */
function scheduleWithMakeupRequest(ScheduleMakeupRequestStatus $status): array
{
    $schedule = Schedule::factory()->create();

    $request = ScheduleMakeupRequest::factory()->create([
        'schedule_id' => $schedule->id,
        'status' => $status,
    ]);

    return [$schedule, $request];
}

it('blocks deleting a schedule with a sent make-up request', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::SENT);

    expect(fn () => $schedule->delete())
        ->toThrow(CannotDeleteScheduleWithMakeupException::class);

    expect(Schedule::query()->whereKey($schedule->id)->exists())->toBeTrue();
});

it('blocks deleting a schedule with a requested make-up request', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::REQUESTED);

    expect(fn () => $schedule->delete())
        ->toThrow(CannotDeleteScheduleWithMakeupException::class);
});

it('blocks deleting a schedule with a scheduled make-up request', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::SCHEDULED);

    expect(fn () => $schedule->delete())
        ->toThrow(CannotDeleteScheduleWithMakeupException::class);
});

it('allows deleting a schedule whose make-up request is declined', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::DECLINED);

    $schedule->delete();

    expect($schedule->trashed())->toBeTrue();
});

it('allows deleting a schedule whose make-up request is not required', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::NOT_REQUIRED);

    $schedule->delete();

    expect($schedule->trashed())->toBeTrue();
});

it('allows deleting a schedule with a pending make-up request', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::PENDING);

    $schedule->delete();

    expect($schedule->trashed())->toBeTrue();
});

it('allows deleting a schedule that has no make-up request', function () {
    $schedule = Schedule::factory()->create();

    $schedule->delete();

    expect($schedule->trashed())->toBeTrue();
});

it('allows force-deleting a schedule even with a blocking make-up request', function () {
    [$schedule] = scheduleWithMakeupRequest(ScheduleMakeupRequestStatus::SCHEDULED);

    $schedule->forceDelete();

    expect(Schedule::query()->withTrashed()->whereKey($schedule->id)->exists())->toBeFalse();
});
