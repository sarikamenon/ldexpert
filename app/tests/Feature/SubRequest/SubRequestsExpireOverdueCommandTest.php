<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Enums\SubRequestInviteeStatus;
use App\Enums\SubRequestStatus;
use App\Models\ScheduleSubRequestInvitee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

it('runs the expire-overdue command and reports the count', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id, $w['C']->id], null);

    Carbon::setTestNow($w['sessionStart']->copy()->subHour());

    $this->artisan('sub-requests:expire-overdue')
        ->expectsOutputToContain('Expired 1 sub request(s).')
        ->assertSuccessful();

    expect($request->fresh()->status)->toBe(SubRequestStatus::EXPIRED);
    expect(ScheduleSubRequestInvitee::where('schedule_sub_request_id', $request->id)
        ->where('status', SubRequestInviteeStatus::EXPIRED->value)
        ->count())->toBe(2);

    Carbon::setTestNow();
});

it('reports 0 when nothing is overdue', function () {
    $w = $this->buildSubCoverageWorld();
    app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $this->artisan('sub-requests:expire-overdue')
        ->expectsOutputToContain('Expired 0 sub request(s).')
        ->assertSuccessful();
});
