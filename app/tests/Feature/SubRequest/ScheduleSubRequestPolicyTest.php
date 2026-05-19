<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Models\User;
use App\Policies\ScheduleSubRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

function subRequestPolicy(): ScheduleSubRequestPolicy
{
    return new ScheduleSubRequestPolicy;
}

it('createSubRequest allows the assigned therapist and rejects others', function () {
    $w = $this->buildSubCoverageWorld();

    expect(subRequestPolicy()->createSubRequest($w['A'], $w['schedule']))->toBeTrue();
    expect(subRequestPolicy()->createSubRequest($w['B'], $w['schedule']))->toBeFalse();
});

it('accept and decline require an invited invitee row', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $request->refresh();
    expect(subRequestPolicy()->accept($w['B'], $request))->toBeTrue();
    expect(subRequestPolicy()->decline($w['B'], $request))->toBeTrue();

    // C was not invited
    expect(subRequestPolicy()->accept($w['C'], $request))->toBeFalse();
    expect(subRequestPolicy()->decline($w['C'], $request))->toBeFalse();

    // Requester can never accept their own
    expect(subRequestPolicy()->accept($w['A'], $request))->toBeFalse();
});

it('accept and decline return false once the request is no longer open', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);
    app(ScheduleSubRequestService::class)->accept($w['B'], $request->fresh());

    $request->refresh();
    expect(subRequestPolicy()->accept($w['C'], $request))->toBeFalse();
    expect(subRequestPolicy()->decline($w['C'], $request))->toBeFalse();
});

it('manageInvitees allows requester and admin, blocks others and once closed', function () {
    $w = $this->buildSubCoverageWorld();
    $admin = User::factory()->admin()->create();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $request->refresh();
    expect(subRequestPolicy()->manageInvitees($w['A'], $request))->toBeTrue();
    expect(subRequestPolicy()->manageInvitees($admin, $request))->toBeTrue();
    expect(subRequestPolicy()->manageInvitees($w['B'], $request))->toBeFalse();

    app(ScheduleSubRequestService::class)->cancel($w['A'], $request->fresh());
    expect(subRequestPolicy()->manageInvitees($w['A'], $request->fresh()))->toBeFalse();
});

it('cancel mirrors manageInvitees authorization', function () {
    $w = $this->buildSubCoverageWorld();
    $admin = User::factory()->admin()->create();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $request->refresh();
    expect(subRequestPolicy()->cancel($w['A'], $request))->toBeTrue();
    expect(subRequestPolicy()->cancel($admin, $request))->toBeTrue();
    expect(subRequestPolicy()->cancel($w['B'], $request))->toBeFalse();
});

it('view allows requester, accepted sub, invited therapist, and admin', function () {
    $w = $this->buildSubCoverageWorld();
    $admin = User::factory()->admin()->create();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $request->refresh();
    expect(subRequestPolicy()->view($w['A'], $request))->toBeTrue();
    expect(subRequestPolicy()->view($w['B'], $request))->toBeTrue();
    expect(subRequestPolicy()->view($admin, $request))->toBeTrue();
    expect(subRequestPolicy()->view($w['C'], $request))->toBeFalse();
});
