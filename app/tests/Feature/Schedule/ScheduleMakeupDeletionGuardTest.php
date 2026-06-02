<?php

declare(strict_types=1);

use App\Domain\Therapist\Services\ScheduleService;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Exceptions\CannotDeleteScheduleWithMakeupException;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
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

// ─── Recurring cascade ───────────────────────────────────────────────────────

it('aborts the recurring cascade and rolls back the whole batch when one future occurrence is blocked', function () {
    $therapist = User::factory()->therapist()->create();
    $batch = 'REC_'.str_repeat('a', 20);

    // Three future occurrences in one recurring batch; the middle one carries a SENT request.
    $occurrences = collect(['2026-07-01', '2026-07-08', '2026-07-15'])->map(
        fn (string $date) => Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurring_batch_number' => $batch,
            'schedule_date' => $date,
        ])
    );

    ScheduleMakeupRequest::factory()->create([
        'schedule_id' => $occurrences[1]->id,
        'status' => ScheduleMakeupRequestStatus::SENT,
    ]);

    $service = app(ScheduleService::class);

    expect(fn () => $service->deleteFutureRecurringSchedules($therapist, $occurrences[0]->id))
        ->toThrow(CannotDeleteScheduleWithMakeupException::class);

    // Single chokepoint + transaction: NONE of the batch is soft-deleted.
    $occurrences->each(
        fn (Schedule $s) => expect(Schedule::query()->whereKey($s->id)->exists())->toBeTrue()
    );
});

it('deletes the whole recurring batch when no occurrence is blocked', function () {
    $therapist = User::factory()->therapist()->create();
    $batch = 'REC_'.str_repeat('b', 20);

    $occurrences = collect(['2026-07-01', '2026-07-08', '2026-07-15'])->map(
        fn (string $date) => Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurring_batch_number' => $batch,
            'schedule_date' => $date,
        ])
    );

    $service = app(ScheduleService::class);
    $deleted = $service->deleteFutureRecurringSchedules($therapist, $occurrences[0]->id);

    expect($deleted)->toBe(3);
    $occurrences->each(
        fn (Schedule $s) => expect($s->fresh()->trashed())->toBeTrue()
    );
});

// ─── Closure-event delete soft-deletes pending rows only ─────────────────────

it('soft-deletes only PENDING make-up rows when the closure event is deleted', function () {
    $event = SchoolCalendarEvent::factory()->create();

    $pending = ScheduleMakeupRequest::factory()->create([
        'school_calendar_event_id' => $event->id,
        'status' => ScheduleMakeupRequestStatus::PENDING,
    ]);
    $sent = ScheduleMakeupRequest::factory()->sent()->create([
        'school_calendar_event_id' => $event->id,
    ]);
    $scheduled = ScheduleMakeupRequest::factory()->create([
        'school_calendar_event_id' => $event->id,
        'status' => ScheduleMakeupRequestStatus::SCHEDULED,
    ]);

    $event->delete();

    // Pending row is soft-deleted; sent/scheduled rows are left intact.
    expect(ScheduleMakeupRequest::query()->whereKey($pending->id)->exists())->toBeFalse();
    expect(ScheduleMakeupRequest::withTrashed()->find($pending->id)->trashed())->toBeTrue();
    expect($sent->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT);
    expect($scheduled->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SCHEDULED);
});

it('leaves make-up rows untouched when the closure event is force-deleted', function () {
    $event = SchoolCalendarEvent::factory()->create();

    $pending = ScheduleMakeupRequest::factory()->create([
        'school_calendar_event_id' => $event->id,
        'status' => ScheduleMakeupRequestStatus::PENDING,
    ]);

    $event->forceDelete();

    expect($pending->fresh())->not->toBeNull();
});
