<?php

declare(strict_types=1);

use App\Models\AdvanceReconciliation;
use App\Models\BillingSchedule;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('advance reconciliation belongs to its schedule and school', function () {
    $school = School::factory()->create();
    $schedule = BillingSchedule::factory()->forSchool($school)->advance()->create();

    $reconciliation = AdvanceReconciliation::factory()->create([
        'billing_schedule_id' => $schedule->id,
        'school_id' => $school->id,
    ]);

    expect($reconciliation->billingSchedule->id)->toBe($schedule->id)
        ->and($reconciliation->school->id)->toBe($school->id)
        ->and($reconciliation->reconciled_period_start)->not->toBeNull();
});

test('advance reconciliation uses soft deletes', function () {
    $reconciliation = AdvanceReconciliation::factory()->create();
    $id = $reconciliation->id;

    $reconciliation->delete();

    expect(AdvanceReconciliation::find($id))->toBeNull()
        ->and(AdvanceReconciliation::withTrashed()->find($id))->not->toBeNull();
});
