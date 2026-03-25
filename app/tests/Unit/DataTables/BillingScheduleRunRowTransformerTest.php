<?php

declare(strict_types=1);

use App\DataTables\Transformers\BillingScheduleRunRowTransformer;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('transforms advance mode run with eight columns', function () {
    $schedule = BillingSchedule::factory()->forSchool()->advance()->create();
    $run = BillingScheduleRun::factory()->create([
        'billing_schedule_id' => $schedule->id,
        'sessions_found' => 5,
        'adjustments_count' => 1,
        'adjustment_total' => 10.5,
        'carry_forward_amount' => 20,
        'total_amount' => 100,
    ]);
    $run->load(['invoice', 'therapistBill']);

    $row = BillingScheduleRunRowTransformer::transform($run, $schedule);

    expect($row)->toHaveCount(8)
        ->and($row[2])->toContain('5')
        ->and($row[3])->toContain('1')
        ->and($row[4])->toContain('$20.00')
        ->and($row[5])->toContain('$100.00')
        ->and($row[6])->toContain('Success');
});

test('transforms standard mode run with seven columns', function () {
    $schedule = BillingSchedule::factory()->forSchool()->create();
    $run = BillingScheduleRun::factory()->create([
        'billing_schedule_id' => $schedule->id,
        'sessions_from_prior_periods' => 3,
        'total_amount' => 50,
    ]);
    $run->load(['invoice', 'therapistBill']);

    $row = BillingScheduleRunRowTransformer::transform($run, $schedule);

    expect($row)->toHaveCount(7)
        ->and($row[3])->toContain('3')
        ->and($row[4])->toContain('$50.00');
});
