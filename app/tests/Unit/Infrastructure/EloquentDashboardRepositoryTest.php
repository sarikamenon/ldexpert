<?php

declare(strict_types=1);

use App\Infrastructure\Repositories\EloquentDashboardRepository;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\TherapistProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create an open sub request whose schedule starts at the given UTC instant,
 * owned by a therapist with the given position.
 */
function openSubRequestStartingAt(Carbon\CarbonInterface $startUtc, ?Position $position): ScheduleSubRequest
{
    $profile = TherapistProfile::factory()->create([
        'position_id' => $position?->id,
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $profile->user_id,
        'schedule_date' => $startUtc->format('Y-m-d'),
        'start_time' => $startUtc->format('H:i'),
    ]);

    return ScheduleSubRequest::factory()->create(['schedule_id' => $schedule->id]);
}

test('open sub requests by position returns empty chart when none', function () {
    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getOpenSubRequestsByPosition();

    expect($result['labels'])->toBe([])
        ->and($result['data'])->toBe([])
        ->and($result['colors'])->toBe([]);
});

test('open sub requests are grouped by therapist position', function () {
    $ot = Position::factory()->create(['name' => 'OT']);
    openSubRequestStartingAt(now()->addHour(), $ot);
    openSubRequestStartingAt(now()->addDay(), null);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getOpenSubRequestsByPosition();

    expect($result['labels'])->toBe(['OT', 'Unassigned'])
        ->and($result['data'])->toBe([1, 1])
        ->and($result['colors'])->toHaveCount(2);
});

test('open sub requests for past sessions are excluded', function () {
    $ot = Position::factory()->create(['name' => 'OT']);
    openSubRequestStartingAt(now()->addHour(), $ot);
    openSubRequestStartingAt(now()->subDay(), $ot);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getOpenSubRequestsByPosition();

    expect($result['labels'])->toBe(['OT'])
        ->and($result['data'])->toBe([1]);
});

test('non-open sub requests are excluded', function () {
    $ot = Position::factory()->create(['name' => 'OT']);
    $request = openSubRequestStartingAt(now()->addHour(), $ot);
    $request->update(['status' => App\Enums\SubRequestStatus::CANCELLED->value]);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getOpenSubRequestsByPosition();

    expect($result['labels'])->toBe([])
        ->and($result['data'])->toBe([]);
});

test('pending ssas returns only pending agreements', function () {
    App\Models\ServiceSupportAgreement::factory()->create(['status' => App\Enums\SSAStatus::PENDING->value]);
    App\Models\ServiceSupportAgreement::factory()->create(['status' => App\Enums\SSAStatus::ACTIVE->value]);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getPendingSSAs();

    expect($result)->toHaveCount(1)
        ->and($result->first()->status)->toBe(App\Enums\SSAStatus::PENDING);
});

test('pending ssas respects the limit', function () {
    App\Models\ServiceSupportAgreement::factory()->count(7)->create(['status' => App\Enums\SSAStatus::PENDING->value]);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getPendingSSAs(5);

    expect($result)->toHaveCount(5);
});

test('pending ssas are ordered newest first', function () {
    $older = App\Models\ServiceSupportAgreement::factory()->create([
        'status' => App\Enums\SSAStatus::PENDING->value,
        'created_at' => now()->subDays(2),
    ]);
    $newer = App\Models\ServiceSupportAgreement::factory()->create([
        'status' => App\Enums\SSAStatus::PENDING->value,
        'created_at' => now(),
    ]);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getPendingSSAs();

    expect($result->first()->id)->toBe($newer->id)
        ->and($result->last()->id)->toBe($older->id);
});
