<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\GenerationDayType;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing schedule casts enum fields correctly', function () {
    $schedule = BillingSchedule::factory()->create();

    expect($schedule->schedule_type)->toBeInstanceOf(BillingScheduleType::class)
        ->and($schedule->billing_mode)->toBeInstanceOf(BillingMode::class)
        ->and($schedule->frequency)->toBeInstanceOf(BillingFrequency::class)
        ->and($schedule->generation_day_type)->toBeInstanceOf(GenerationDayType::class);
});

test('billing schedule active scope filters active schedules', function () {
    BillingSchedule::factory()->create(['is_active' => true]);
    BillingSchedule::factory()->create(['is_active' => false]);

    $active = BillingSchedule::active()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->is_active)->toBeTrue();
});

test('billing schedule due scope filters due schedules', function () {
    BillingSchedule::factory()->due()->create();
    BillingSchedule::factory()->create([
        'is_active' => true,
        'auto_generate' => true,
        'next_run_at' => now()->addWeek()->toDateString(),
    ]);
    BillingSchedule::factory()->inactive()->create([
        'next_run_at' => now()->subDay()->toDateString(),
    ]);

    $due = BillingSchedule::due()->get();

    expect($due)->toHaveCount(1);
});

test('billing schedule forSchools scope filters school invoices', function () {
    BillingSchedule::factory()->forSchool()->create();
    BillingSchedule::factory()->forTherapist()->create();

    $schoolSchedules = BillingSchedule::forSchools()->get();

    expect($schoolSchedules)->toHaveCount(1)
        ->and($schoolSchedules->first()->schedule_type)->toBe(BillingScheduleType::SCHOOL_INVOICE);
});

test('billing schedule forTherapists scope filters therapist bills', function () {
    BillingSchedule::factory()->forSchool()->create();
    BillingSchedule::factory()->forTherapist()->create();

    $therapistSchedules = BillingSchedule::forTherapists()->get();

    expect($therapistSchedules)->toHaveCount(1)
        ->and($therapistSchedules->first()->schedule_type)->toBe(BillingScheduleType::THERAPIST_BILL);
});

test('billing schedule isDue returns true when schedule is due', function () {
    $schedule = BillingSchedule::factory()->due()->create();

    expect($schedule->isDue())->toBeTrue();
});

test('billing schedule isDue returns false when not due', function () {
    $futureSchedule = BillingSchedule::factory()->create([
        'next_run_at' => now()->addWeek()->toDateString(),
    ]);
    $inactiveSchedule = BillingSchedule::factory()->inactive()->create([
        'next_run_at' => now()->subDay()->toDateString(),
    ]);
    $noAutoGenerate = BillingSchedule::factory()->create([
        'auto_generate' => false,
        'next_run_at' => now()->subDay()->toDateString(),
    ]);

    expect($futureSchedule->isDue())->toBeFalse()
        ->and($inactiveSchedule->isDue())->toBeFalse()
        ->and($noAutoGenerate->isDue())->toBeFalse();
});

test('billing schedule isForSchool returns correct value', function () {
    $schoolSchedule = BillingSchedule::factory()->forSchool()->create();
    $therapistSchedule = BillingSchedule::factory()->forTherapist()->create();

    expect($schoolSchedule->isForSchool())->toBeTrue()
        ->and($therapistSchedule->isForSchool())->toBeFalse();
});

test('billing schedule isForTherapist returns correct value', function () {
    $therapistSchedule = BillingSchedule::factory()->forTherapist()->create();
    $schoolSchedule = BillingSchedule::factory()->forSchool()->create();

    expect($therapistSchedule->isForTherapist())->toBeTrue()
        ->and($schoolSchedule->isForTherapist())->toBeFalse();
});

test('billing schedule isAdvanceMode returns correct value', function () {
    $advanceSchedule = BillingSchedule::factory()->advance()->create();
    $standardSchedule = BillingSchedule::factory()->create();

    expect($advanceSchedule->isAdvanceMode())->toBeTrue()
        ->and($standardSchedule->isAdvanceMode())->toBeFalse();
});

test('billing schedule has morphTo schedulable relationship', function () {
    $school = School::factory()->create();
    $schedule = BillingSchedule::factory()->forSchool($school)->create();

    expect($schedule->schedulable)->toBeInstanceOf(School::class)
        ->and($schedule->schedulable->id)->toBe($school->id);
});

test('billing schedule has many runs', function () {
    $schedule = BillingSchedule::factory()->create();
    BillingScheduleRun::factory()->count(3)->create(['billing_schedule_id' => $schedule->id]);

    expect($schedule->runs)->toHaveCount(3);
});

test('billing schedule has latest run', function () {
    $schedule = BillingSchedule::factory()->create();
    BillingScheduleRun::factory()->create([
        'billing_schedule_id' => $schedule->id,
        'created_at' => now()->subDay(),
    ]);
    $latest = BillingScheduleRun::factory()->create([
        'billing_schedule_id' => $schedule->id,
        'created_at' => now(),
    ]);

    $schedule->refresh();

    expect($schedule->latestRun)->not->toBeNull()
        ->and($schedule->latestRun->id)->toBe($latest->id);
});

test('billing schedule uses soft deletes', function () {
    $schedule = BillingSchedule::factory()->create();
    $schedule->delete();

    expect(BillingSchedule::withTrashed()->find($schedule->id))->not->toBeNull()
        ->and(BillingSchedule::find($schedule->id))->toBeNull();
});
