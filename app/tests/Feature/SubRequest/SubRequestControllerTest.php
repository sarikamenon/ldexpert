<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Enums\SubRequestInviteeStatus;
use App\Enums\SubRequestStatus;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

// ─── store ─────────────────────────────────────────────────────────────────

it('stores a sub request via POST when the actor owns the schedule', function () {
    $w = $this->buildSubCoverageWorld();

    $this->actingAs($w['A'])
        ->postJson(route('therapist.sub-requests.store-for-schedule', $w['schedule']), [
            'reason' => 'Doctor visit',
            'invitee_ids' => [$w['B']->id, $w['C']->id],
        ])
        ->assertStatus(201);

    expect(ScheduleSubRequest::count())->toBe(1);
    expect(ScheduleSubRequestInvitee::count())->toBe(2);
});

it('rejects store from a non-owner therapist with 403', function () {
    $w = $this->buildSubCoverageWorld();

    $this->actingAs($w['B'])
        ->postJson(route('therapist.sub-requests.store-for-schedule', $w['schedule']), [
            'invitee_ids' => [$w['C']->id],
        ])
        ->assertStatus(403);
});

it('rejects store with empty invitee_ids as a validation error', function () {
    $w = $this->buildSubCoverageWorld();

    $this->actingAs($w['A'])
        ->postJson(route('therapist.sub-requests.store-for-schedule', $w['schedule']), [
            'invitee_ids' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('invitee_ids');
});

it('returns 422 when an invitee is ineligible (domain check)', function () {
    $w = $this->buildSubCoverageWorld();

    $this->actingAs($w['A'])
        ->postJson(route('therapist.sub-requests.store-for-schedule', $w['schedule']), [
            'invitee_ids' => [$w['E']->id],
        ])
        ->assertStatus(422);
});

// ─── accept ────────────────────────────────────────────────────────────────

it('accepts a sub request via POST', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->actingAs($w['B'])
        ->postJson(route('therapist.sub-requests.accept', $request))
        ->assertOk();

    expect($request->fresh()->status)->toBe(SubRequestStatus::ACCEPTED);
});

it('rejects accept by a non-invitee with 403', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->actingAs($w['C'])
        ->postJson(route('therapist.sub-requests.accept', $request))
        ->assertStatus(403);
});

// ─── decline ───────────────────────────────────────────────────────────────

it('declines via POST and keeps the parent request open', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    $this->actingAs($w['B'])
        ->postJson(route('therapist.sub-requests.decline', $request))
        ->assertOk();

    expect($request->fresh()->status)->toBe(SubRequestStatus::OPEN);
    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('therapist_id', $w['B']->id)
        ->value('status'))->toBe(SubRequestInviteeStatus::DECLINED);
});

// ─── cancel ────────────────────────────────────────────────────────────────

it('cancels via POST when the actor is the requester', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->actingAs($w['A'])
        ->postJson(route('therapist.sub-requests.cancel', $request))
        ->assertOk();

    expect($request->fresh()->status)->toBe(SubRequestStatus::CANCELLED);
});

it('rejects cancel from a non-owner therapist with 403', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->actingAs($w['B'])
        ->postJson(route('therapist.sub-requests.cancel', $request))
        ->assertStatus(403);
});

// ─── updateInvitees ────────────────────────────────────────────────────────

it('syncs invitees via PATCH', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->actingAs($w['A'])
        ->patchJson(route('therapist.sub-requests.invitees.update', $request), [
            'invitee_ids' => [$w['B']->id, $w['C']->id],
        ])
        ->assertOk();

    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)->count())->toBe(2);
});

it('rejects PATCH from non-owner therapist with 403', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->actingAs($w['B'])
        ->patchJson(route('therapist.sub-requests.invitees.update', $request), [
            'invitee_ids' => [$w['C']->id],
        ])
        ->assertStatus(403);
});

// ─── eligibleSubs ──────────────────────────────────────────────────────────

it('returns eligible subs for create-time picker via GET', function () {
    $w = $this->buildSubCoverageWorld();

    $response = $this->actingAs($w['A'])
        ->getJson(route('therapist.sub-requests.eligible-subs', [
            'service_id' => $w['service']->id,
            'date' => $w['sessionDate']->toDateString(),
        ]))
        ->assertOk();

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids)->toContain($w['B']->id, $w['C']->id);
    expect($ids)->not->toContain($w['A']->id, $w['E']->id, $w['F']->id);
});

it('returns eligible subs annotated with status for an existing request via GET', function () {
    $w = $this->buildSubCoverageWorld();
    app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $response = $this->actingAs($w['A'])
        ->getJson(route('therapist.sub-requests.eligible-subs', [
            'schedule_id' => $w['schedule']->id,
        ]))
        ->assertOk();

    $byId = collect($response->json())->keyBy('id');
    expect($byId[$w['B']->id]['invitee_status'])->toBe('selected');
    expect($byId[$w['C']->id]['invitee_status'])->toBe('none');
});

// ─── index ─────────────────────────────────────────────────────────────────

it('renders the sub-requests index for an authenticated therapist', function () {
    $w = $this->buildSubCoverageWorld();

    $this->actingAs($w['B'])
        ->get(route('therapist.sub-requests.index'))
        ->assertOk();
});
