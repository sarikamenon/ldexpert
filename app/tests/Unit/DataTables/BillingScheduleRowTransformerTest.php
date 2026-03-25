<?php

declare(strict_types=1);

use App\DataTables\Transformers\BillingScheduleRowTransformer;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('transforms billing schedule to row array', function () {
    $schedule = BillingSchedule::factory()->forSchool()->create();
    $schedule->load('schedulable', 'latestRun');

    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row)->toHaveCount(8)
        ->and($row[1])->toContain('School Invoice')
        ->and($row[3])->toContain('Semi-Monthly');
});

test('transforms advance mode schedule with warning badge', function () {
    $schedule = BillingSchedule::factory()->forSchool()->advance()->create();
    $schedule->load('schedulable', 'latestRun');

    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row[2])->toContain('bg-warning');
});

test('transforms active schedule with success badge', function () {
    $schedule = BillingSchedule::factory()->create(['is_active' => true]);
    $schedule->load('schedulable', 'latestRun');

    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row[6])->toContain('Active')
        ->and($row[6])->toContain('bg-success');
});

test('transforms inactive schedule with secondary badge', function () {
    $schedule = BillingSchedule::factory()->inactive()->create();
    $schedule->load('schedulable', 'latestRun');

    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row[6])->toContain('Inactive');
});

test('edit link targets school billing tab for school invoice schedules', function () {
    $school = School::factory()->create();
    $schedule = BillingSchedule::factory()->forSchool($school)->create();
    $schedule->load('schedulable', 'latestRun');

    $expected = route('admin.schools.show', ['school' => $school, 'tab' => 'billing']);
    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row[7])->toContain(e($expected));
});

test('edit link targets therapist billing tab for therapist bill schedules', function () {
    $therapist = User::factory()->therapist()->create();
    $schedule = BillingSchedule::factory()->forTherapist($therapist)->create();
    $schedule->load('schedulable', 'latestRun');

    $expected = route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'billing']);
    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row[7])->toContain(e($expected));
});

test('actions include primary-styled history link', function () {
    $schedule = BillingSchedule::factory()->forSchool()->create();
    $schedule->load('schedulable', 'latestRun');

    $row = BillingScheduleRowTransformer::transform($schedule);

    expect($row[7])->toContain('bg-primary')
        ->and($row[7])->toContain('Run History');
});

test('includes run status indicator from latest run', function () {
    $schedule = BillingSchedule::factory()->create();
    BillingScheduleRun::factory()->success()->create([
        'billing_schedule_id' => $schedule->id,
    ]);

    $schedule->load('schedulable', 'latestRun');

    $row = BillingScheduleRowTransformer::transform($schedule);

    // Column 5 is last run, should have checkmark
    expect($row[5])->toContain('✓');
});
