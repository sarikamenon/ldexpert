<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Enums\ScheduleSubCoverageStatus;
use App\Enums\SubRequestInviteeStatus;
use App\Enums\SubRequestStatus;
use App\Mail\SubRequestInvitationMail;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use App\Models\ScheduleSubSsa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

/**
 * Resolve a fresh service instance from the container.
 */
function subService(): ScheduleSubRequestService
{
    return app(ScheduleSubRequestService::class);
}

// ─── create() ──────────────────────────────────────────────────────────────

it('creates an open sub request with one invitee row per id', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], 'Conference');

    expect($request->status)->toBe(SubRequestStatus::OPEN);
    expect($request->requested_by_id)->toBe($w['A']->id);
    expect($request->reason)->toBe('Conference');

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)->count())->toBe(2);
    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('status', SubRequestInviteeStatus::INVITED->value)
        ->count())->toBe(2);

    $w['schedule']->refresh();
    expect($w['schedule']->sub_request_status?->value)->toBe(ScheduleSubCoverageStatus::REQUESTED->value);
});

it('queues invitation emails to every invitee on create', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    Mail::assertQueued(SubRequestInvitationMail::class, 2);
    Mail::assertQueued(SubRequestInvitationMail::class, fn ($m) => $m->hasTo($w['B']->email));
    Mail::assertQueued(SubRequestInvitationMail::class, fn ($m) => $m->hasTo($w['C']->email));
});

it('rejects create when the actor is not the schedule owner', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['B'], $w['schedule'], [$w['C']->id], null);
})->throws(InvalidArgumentException::class, 'Only the assigned therapist');

it('rejects create when the invitee list is empty', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['A'], $w['schedule'], [], null);
})->throws(InvalidArgumentException::class, 'At least one invitee');

it('rejects create within the cutoff window', function () {
    // Session 1 hour from now, cutoff is 2 hours
    $w = $this->buildSubCoverageWorld(Carbon::now()->addHour()->setSecond(0));

    subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);
})->throws(InvalidArgumentException::class, 'before the session starts');

it('rejects create when an open request already exists for the schedule', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    subService()->create($w['A'], $w['schedule'], [$w['C']->id], null);
})->throws(InvalidArgumentException::class, 'already exists');

it('rejects create when an invitee has the wrong position', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['A'], $w['schedule'], [$w['E']->id], null);
})->throws(InvalidArgumentException::class, 'not eligible');

it('rejects create when an invitee lacks a contract for this service', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['A'], $w['schedule'], [$w['F']->id], null);
})->throws(InvalidArgumentException::class, 'not eligible');

// ─── accept() ──────────────────────────────────────────────────────────────

it('accepts a request, supersedes other invitees, and writes a sub-SSA snapshot', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    subService()->accept($w['B'], $request->fresh());

    $request->refresh();
    expect($request->status)->toBe(SubRequestStatus::ACCEPTED);
    expect($request->accepted_by_id)->toBe($w['B']->id);
    expect($request->accepted_at)->not->toBeNull();

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::ACCEPTED);

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['C']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::SUPERSEDED);

    $w['schedule']->refresh();
    expect($w['schedule']->sub_therapist_id)->toBe($w['B']->id);
    expect($w['schedule']->sub_request_status?->value)->toBe(ScheduleSubCoverageStatus::ACCEPTED->value);

    $snapshot = ScheduleSubSsa::where('schedule_sub_request_id', $request->id)->first();
    expect($snapshot)->not->toBeNull();
    expect($snapshot->ssa_id)->toBe($w['ssa']->id);
    expect($snapshot->sub_therapist_id)->toBe($w['B']->id);
    expect($snapshot->student_id)->toBe($w['student']->id);
    expect($snapshot->service_id)->toBe($w['service']->id);
});

it('rejects accept when caller has no invitee row on the request', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    // C is eligible but was never invited
    subService()->accept($w['C'], $request->fresh());
})->throws(InvalidArgumentException::class, 'not been invited');

it('rejects accept when the request is no longer open', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);
    subService()->accept($w['B'], $request->fresh());

    // C tries to accept after B already won.
    subService()->accept($w['C'], $request->fresh());
})->throws(InvalidArgumentException::class, 'already accepted');

it('rejects accept by the requester themselves', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    subService()->accept($w['A'], $request->fresh());
})->throws(InvalidArgumentException::class, 'cannot accept your own');

it('rejects accept within the cutoff window even if request still open', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    // Travel forward so we are inside the cutoff window
    Carbon::setTestNow($w['sessionStart']->copy()->subMinutes(30));

    try {
        subService()->accept($w['B'], $request->fresh());
        expect(false)->toBeTrue('Should have thrown');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('no longer be accepted');
    } finally {
        Carbon::setTestNow();
    }
});

// ─── decline() ─────────────────────────────────────────────────────────────

it('declines an invitation without closing the parent request', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    subService()->decline($w['B'], $request->fresh());

    $bRow = ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->first();
    expect($bRow->status)->toBe(SubRequestInviteeStatus::DECLINED);
    expect($bRow->responded_at)->not->toBeNull();

    expect($request->fresh()->status)->toBe(SubRequestStatus::OPEN);

    // C's row untouched
    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['C']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::INVITED);
});

it('rejects decline when the caller has no active invitation', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    // C was never invited
    subService()->decline($w['C'], $request->fresh());
})->throws(InvalidArgumentException::class, 'active invitation');

it('allows re-invitation after decline and queues a fresh email', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);
    subService()->decline($w['B'], $request->fresh());

    Mail::fake(); // reset

    subService()->syncInvitees($w['A'], $request->fresh(), [$w['B']->id]);

    $row = ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->first();
    expect($row->status)->toBe(SubRequestInviteeStatus::INVITED);
    expect($row->responded_at)->toBeNull();

    Mail::assertQueued(SubRequestInvitationMail::class, 1);
});

// ─── cancel() ──────────────────────────────────────────────────────────────

it('cancels an open request, supersedes invitees, and clears schedule coverage cols', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    subService()->cancel($w['A'], $request->fresh());

    $request->refresh();
    expect($request->status)->toBe(SubRequestStatus::CANCELLED);
    expect($request->cancelled_at)->not->toBeNull();

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('status', SubRequestInviteeStatus::SUPERSEDED->value)
        ->count())->toBe(2);

    $w['schedule']->refresh();
    expect($w['schedule']->sub_therapist_id)->toBeNull();
    expect($w['schedule']->sub_request_status)->toBeNull();
});

it('rejects cancel by a non-owner non-admin', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    subService()->cancel($w['B'], $request->fresh());
})->throws(InvalidArgumentException::class, 'permission');

it('rejects cancel of an already-accepted request', function () {
    $w = $this->buildSubCoverageWorld();

    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);
    subService()->accept($w['B'], $request->fresh());

    subService()->cancel($w['A'], $request->fresh());
})->throws(InvalidArgumentException::class, 'Only open');

// ─── expireOverdue() ───────────────────────────────────────────────────────

it('expires open requests whose schedule is inside the cutoff window', function () {
    $w = $this->buildSubCoverageWorld();
    $request = subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    // Move clock to 1 hour before the session — inside the 2h cutoff
    Carbon::setTestNow($w['sessionStart']->copy()->subHour());

    $count = subService()->expireOverdue();

    expect($count)->toBe(1);

    $request->refresh();
    expect($request->status)->toBe(SubRequestStatus::EXPIRED);

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('status', SubRequestInviteeStatus::EXPIRED->value)
        ->count())->toBe(2);

    $w['schedule']->refresh();
    expect($w['schedule']->sub_therapist_id)->toBeNull();
    expect($w['schedule']->sub_request_status)->toBeNull();

    Carbon::setTestNow();
});

it('does not expire requests whose schedule is outside the cutoff window', function () {
    $w = $this->buildSubCoverageWorld();
    subService()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    expect(subService()->expireOverdue())->toBe(0);

    expect(ScheduleSubRequest::query()->where('status', SubRequestStatus::OPEN->value)->count())->toBe(1);
});

// ─── countMyOpenRequests / countOpenForTherapist ───────────────────────────

it('counts open requests separately for requester and invitee', function () {
    $w = $this->buildSubCoverageWorld();

    subService()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    expect(subService()->countMyOpenRequests($w['A']))->toBe(1);
    expect(subService()->countOpenForTherapist($w['B']))->toBe(1);
    expect(subService()->countOpenForTherapist($w['C']))->toBe(1);

    // Therapist E (ineligible, never invited) sees nothing
    expect(subService()->countOpenForTherapist($w['E']))->toBe(0);
});
