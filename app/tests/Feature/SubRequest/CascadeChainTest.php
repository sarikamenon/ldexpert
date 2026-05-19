<?php

declare(strict_types=1);

use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('soft-deletes sub-requests and invitees when the parent schedule is soft-deleted', function () {
    $schedule = Schedule::factory()->create();
    $request = ScheduleSubRequest::factory()->create(['schedule_id' => $schedule->id]);
    $invitee = ScheduleSubRequestInvitee::factory()->create(['schedule_sub_request_id' => $request->id]);

    $schedule->delete();

    expect(Schedule::withTrashed()->find($schedule->id)?->trashed())->toBeTrue();
    expect(ScheduleSubRequest::withTrashed()->find($request->id)?->trashed())->toBeTrue();
    expect(ScheduleSubRequestInvitee::withTrashed()->find($invitee->id)?->trashed())->toBeTrue();
});

it('restores the cascade in reverse when the parent schedule is restored', function () {
    $schedule = Schedule::factory()->create();
    $request = ScheduleSubRequest::factory()->create(['schedule_id' => $schedule->id]);
    $invitee = ScheduleSubRequestInvitee::factory()->create(['schedule_sub_request_id' => $request->id]);

    $schedule->delete();
    $schedule->refresh();

    Schedule::withTrashed()->find($schedule->id)?->restore();

    expect(Schedule::find($schedule->id))->not->toBeNull();
    expect(ScheduleSubRequest::find($request->id))->not->toBeNull();
    expect(ScheduleSubRequestInvitee::find($invitee->id))->not->toBeNull();
});

it('does not restore unrelated previously-trashed sub-requests when the schedule is restored', function () {
    $schedule = Schedule::factory()->create();

    $oldRequest = ScheduleSubRequest::factory()->create(['schedule_id' => $schedule->id]);
    $oldRequest->delete();

    // Drift the deleted_at of the unrelated row so it does not match the
    // schedule's deleted_at after the cascade.
    ScheduleSubRequest::withTrashed()
        ->where('id', $oldRequest->id)
        ->update(['deleted_at' => now()->subDay()]);

    $activeRequest = ScheduleSubRequest::factory()->create(['schedule_id' => $schedule->id]);

    $schedule->delete();
    $schedule->refresh();

    Schedule::withTrashed()->find($schedule->id)?->restore();

    expect(ScheduleSubRequest::find($activeRequest->id))->not->toBeNull();
    expect(ScheduleSubRequest::find($oldRequest->id))->toBeNull();
});
