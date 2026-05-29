<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Presenters\MakeupRequestPresenter;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->presenter = app(MakeupRequestPresenter::class);
});

it('prefixes the session label with the schedule service name', function () {
    $service = Service::factory()->create(['name' => 'Speech Therapy']);
    $schedule = Schedule::factory()->create(['service_id' => $service->id]);
    $request = ScheduleMakeupRequest::factory()->create(['schedule_id' => $schedule->id]);

    $label = $this->presenter->sessionLabelWithService($request);

    expect($label)->toStartWith('Speech Therapy — ')
        ->and($label)->toContain($this->presenter->sessionLabel($request));
});

it('falls back to "Session" when the linked schedule is soft-deleted', function () {
    // schedule_id is NOT NULL, so the null-relation case only arises when the
    // original schedule row has been soft-deleted.
    $schedule = Schedule::factory()->create();
    $request = ScheduleMakeupRequest::factory()->create(['schedule_id' => $schedule->id]);
    $schedule->delete();
    $request->refresh()->load('schedule');

    expect($this->presenter->sessionLabelWithService($request))->toStartWith('Session — ');
});

it('maps a batch to service-prefixed labels preserving order', function () {
    $serviceA = Service::factory()->create(['name' => 'Service A']);
    $serviceB = Service::factory()->create(['name' => 'Service B']);

    $rowA = ScheduleMakeupRequest::factory()->create([
        'schedule_id' => Schedule::factory()->create(['service_id' => $serviceA->id])->id,
    ]);
    $rowB = ScheduleMakeupRequest::factory()->create([
        'schedule_id' => Schedule::factory()->create(['service_id' => $serviceB->id])->id,
    ]);

    /** @var Collection<int, ScheduleMakeupRequest> $batch */
    $batch = new Collection([$rowA, $rowB]);

    $labels = $this->presenter->sessionLabelsWithService($batch);

    expect($labels)->toHaveCount(2)
        ->and($labels[0])->toStartWith('Service A — ')
        ->and($labels[1])->toStartWith('Service B — ');
});
