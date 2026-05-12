<?php

declare(strict_types=1);

use App\Enums\SSAGoalStatus;
use App\Infrastructure\Repositories\EloquentSSAGoalRepository;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('getMetricsForSsa returns zeros when SSA has no goals', function () {
    $ssa = ServiceSupportAgreement::factory()->create();
    $repo = new EloquentSSAGoalRepository;

    expect($repo->getMetricsForSsa($ssa->id))->toMatchArray([
        'total_goals' => 0,
        'active_goals' => 0,
        'mastered_goals' => 0,
        'discontinued_goals' => 0,
        'mastery_rate' => 0.0,
    ]);
});

it('getMetricsForSsa uses mastered divided by total for mastery_rate', function () {
    $ssa = ServiceSupportAgreement::factory()->create();
    $base = [
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
    ];

    SSAGoal::factory()->count(2)->create(array_merge($base, [
        'status' => SSAGoalStatus::ACTIVE->value,
    ]));
    SSAGoal::factory()->mastered()->create($base);
    SSAGoal::factory()->mastered()->create($base);
    SSAGoal::factory()->discontinued()->create($base);

    $repo = new EloquentSSAGoalRepository;
    $metrics = $repo->getMetricsForSsa($ssa->id);

    expect($metrics['total_goals'])->toBe(5)
        ->and($metrics['active_goals'])->toBe(2)
        ->and($metrics['mastered_goals'])->toBe(2)
        ->and($metrics['discontinued_goals'])->toBe(1)
        ->and($metrics['mastery_rate'])->toBe(40.0);
});
