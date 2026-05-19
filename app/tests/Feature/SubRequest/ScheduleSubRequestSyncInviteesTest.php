<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Enums\SubRequestInviteeStatus;
use App\Mail\SubRequestInvitationMail;
use App\Models\ScheduleSubRequestInvitee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

function svc(): ScheduleSubRequestService
{
    return app(ScheduleSubRequestService::class);
}

it('adds a new invitee row when a new id appears in the payload', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    svc()->syncInvitees($w['A'], $request->fresh(), [$w['B']->id, $w['C']->id]);

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['C']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::INVITED);
});

it('withdraws an invitee removed from the payload', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    svc()->syncInvitees($w['A'], $request->fresh(), [$w['C']->id]);

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::WITHDRAWN);
    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['C']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::INVITED);
});

it('flips a declined invitee back to invited when re-selected and clears responded_at', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);
    svc()->decline($w['B'], $request->fresh());

    svc()->syncInvitees($w['A'], $request->fresh(), [$w['B']->id]);

    $row = ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->first();
    expect($row->status)->toBe(SubRequestInviteeStatus::INVITED);
    expect($row->responded_at)->toBeNull();
});

it('queues a fresh invitation email only for newly-invited and re-invited rows', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);
    Mail::fake();

    // Add C as new invitee, leave B as-is
    svc()->syncInvitees($w['A'], $request->fresh(), [$w['B']->id, $w['C']->id]);

    Mail::assertQueued(SubRequestInvitationMail::class, 1);
    Mail::assertQueued(SubRequestInvitationMail::class, fn ($m) => $m->hasTo($w['C']->email));
});

it('is a no-op for an invitee whose row is already invited and in the payload', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $row = ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->first();
    $originalUpdatedAt = $row->updated_at?->copy();

    Carbon::setTestNow(now()->addSeconds(5));
    svc()->syncInvitees($w['A'], $request->fresh(), [$w['B']->id]);
    Carbon::setTestNow();

    $row->refresh();
    expect($row->status)->toBe(SubRequestInviteeStatus::INVITED);
    // Was not re-written; the updated_at stays the same
    expect($row->updated_at?->equalTo($originalUpdatedAt))->toBeTrue();
});

it('rejects sync when the request is not open', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);
    svc()->accept($w['B'], $request->fresh());

    svc()->syncInvitees($w['A'], $request->fresh(), [$w['C']->id]);
})->throws(InvalidArgumentException::class, 'while the request is open');

it('rejects sync from a non-owner non-admin', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    svc()->syncInvitees($w['B'], $request->fresh(), [$w['C']->id]);
})->throws(InvalidArgumentException::class, 'permission');

it('rejects sync with empty payload', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    svc()->syncInvitees($w['A'], $request->fresh(), []);
})->throws(InvalidArgumentException::class, 'At least one invitee');

it('rejects sync when an invitee is ineligible', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    svc()->syncInvitees($w['A'], $request->fresh(), [$w['B']->id, $w['E']->id]);
})->throws(InvalidArgumentException::class, 'not eligible');

it('rejects sync within the cutoff window', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    Carbon::setTestNow($w['sessionStart']->copy()->subMinutes(30));
    try {
        svc()->syncInvitees($w['A'], $request->fresh(), [$w['C']->id]);
        expect(false)->toBeTrue('Should have thrown');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('cannot be changed within');
    } finally {
        Carbon::setTestNow();
    }
});

it('allows an admin actor to sync invitees', function () {
    $w = $this->buildSubCoverageWorld();
    $admin = User::factory()->admin()->create();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id], null);

    svc()->syncInvitees($admin, $request->fresh(), [$w['C']->id]);

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['C']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::INVITED);
    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::WITHDRAWN);
});

it('does not touch terminal-status rows when their therapist is not in the payload', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svc()->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);
    svc()->decline($w['B'], $request->fresh());

    svc()->syncInvitees($w['A'], $request->fresh(), [$w['C']->id]);

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::DECLINED);
});
