<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Enums\ContractStatus;
use App\Models\TherapistContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

function subRepo(): ScheduleSubRequestRepositoryInterface
{
    return app(ScheduleSubRequestRepositoryInterface::class);
}

function svcE(): ScheduleSubRequestService
{
    return app(ScheduleSubRequestService::class);
}

it('lists every eligible peer and excludes the requester', function () {
    $w = $this->buildSubCoverageWorld();

    $eligible = svcE()->listEligibleSubsFor($w['schedule']);
    $ids = $eligible->map(fn ($dto) => $dto->id)->all();

    expect($ids)->toContain($w['B']->id, $w['C']->id);
    expect($ids)->not->toContain($w['A']->id);  // requester
    expect($ids)->not->toContain($w['E']->id);  // different position
    expect($ids)->not->toContain($w['F']->id);  // no contract for service
});

it('annotates picker entries with invitee_status based on existing rows', function () {
    $w = $this->buildSubCoverageWorld();
    $request = svcE()->create($w['A'], $w['schedule'], [$w['B']->id], null);
    svcE()->decline($w['B'], $request->fresh());
    svcE()->syncInvitees($w['A'], $request->fresh(), [$w['C']->id]);

    $eligible = svcE()->listEligibleSubsFor($w['schedule']);
    $byId = $eligible->keyBy('id');

    expect($byId[$w['B']->id]->inviteeStatus)->toBe('declined');
    expect($byId[$w['C']->id]->inviteeStatus)->toBe('selected');
});

it('filterEligibleIds drops ineligible ids and returns only eligible ones', function () {
    $w = $this->buildSubCoverageWorld();

    $eligible = subRepo()->filterEligibleIds(
        [$w['A']->id, $w['B']->id, $w['C']->id, $w['E']->id, $w['F']->id],
        $w['schedule'],
    );

    sort($eligible);
    $expected = [$w['B']->id, $w['C']->id];
    sort($expected);
    expect($eligible)->toBe($expected);
});

it('excludes therapists whose contract ended before the schedule date', function () {
    $w = $this->buildSubCoverageWorld();

    // End B's contract a day before the schedule
    TherapistContract::query()
        ->where('therapist_id', $w['B']->therapistProfile->id)
        ->update([
            'end_date' => $w['sessionDate']->copy()->subDay()->toDateString(),
        ]);

    $eligible = subRepo()->filterEligibleIds([$w['B']->id, $w['C']->id], $w['schedule']);

    expect($eligible)->toBe([$w['C']->id]);
});

it('excludes therapists whose contract status is not ACTIVE', function () {
    $w = $this->buildSubCoverageWorld();

    TherapistContract::query()
        ->where('therapist_id', $w['C']->therapistProfile->id)
        ->update(['status' => ContractStatus::INACTIVE->value]);

    $eligible = subRepo()->filterEligibleIds([$w['B']->id, $w['C']->id], $w['schedule']);

    expect($eligible)->toBe([$w['B']->id]);
});

it('returns no eligible subs at create-time when the requester has no position', function () {
    $w = $this->buildSubCoverageWorld();
    $w['A']->therapistProfile->update(['position_id' => null]);

    $eligible = svcE()->listEligibleSubsForCreate(
        $w['A']->fresh(),
        (int) $w['service']->id,
        $w['sessionDate']->toDateString(),
    );

    expect($eligible)->toHaveCount(0);
});
