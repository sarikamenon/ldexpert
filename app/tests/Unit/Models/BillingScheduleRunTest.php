<?php

declare(strict_types=1);

use App\Enums\BillingScheduleRunStatus;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing schedule run casts status enum correctly', function () {
    $run = BillingScheduleRun::factory()->create();

    expect($run->status)->toBeInstanceOf(BillingScheduleRunStatus::class);
});

test('billing schedule run isSuccess returns true for success status', function () {
    $run = BillingScheduleRun::factory()->success()->create();

    expect($run->isSuccess())->toBeTrue()
        ->and($run->isFailed())->toBeFalse()
        ->and($run->wasSkipped())->toBeFalse();
});

test('billing schedule run isFailed returns true for failed status', function () {
    $run = BillingScheduleRun::factory()->failed()->create();

    expect($run->isFailed())->toBeTrue()
        ->and($run->isSuccess())->toBeFalse()
        ->and($run->wasSkipped())->toBeFalse();
});

test('billing schedule run wasSkipped returns true for skipped status', function () {
    $run = BillingScheduleRun::factory()->skipped()->create();

    expect($run->wasSkipped())->toBeTrue()
        ->and($run->isSuccess())->toBeFalse()
        ->and($run->isFailed())->toBeFalse();
});

test('billing schedule run belongs to billing schedule', function () {
    $schedule = BillingSchedule::factory()->create();
    $run = BillingScheduleRun::factory()->create(['billing_schedule_id' => $schedule->id]);

    expect($run->billingSchedule)->toBeInstanceOf(BillingSchedule::class)
        ->and($run->billingSchedule->id)->toBe($schedule->id);
});
