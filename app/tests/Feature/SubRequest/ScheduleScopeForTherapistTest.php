<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

it('forTherapist matches schedules assigned via therapist_id', function () {
    $w = $this->buildSubCoverageWorld();

    $ids = Schedule::query()->forTherapist($w['A'])->pluck('id')->all();

    expect($ids)->toContain($w['schedule']->id);
});

it('forTherapist matches schedules covered by the user as a sub', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);
    app(ScheduleSubRequestService::class)->accept($w['B'], $request->fresh());

    $ids = Schedule::query()->forTherapist($w['B'])->pluck('id')->all();

    expect($ids)->toContain($w['schedule']->id);
});

it('forTherapist excludes schedules where the user is neither therapist nor sub', function () {
    $w = $this->buildSubCoverageWorld();

    $ids = Schedule::query()->forTherapist($w['C'])->pluck('id')->all();

    expect($ids)->not->toContain($w['schedule']->id);
});
