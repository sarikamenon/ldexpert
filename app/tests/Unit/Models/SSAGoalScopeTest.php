<?php

declare(strict_types=1);

use App\Enums\SSAGoalStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function scopeSsa(): ServiceSupportAgreement
{
    return ServiceSupportAgreement::factory()->create();
}

function scopeGoal(ServiceSupportAgreement $ssa, array $attrs = []): SSAGoal
{
    return SSAGoal::factory()->create(array_merge([
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
    ], $attrs));
}

// ---------------------------------------------------------------------------
// activeStatus scope
// ---------------------------------------------------------------------------

it('activeStatus scope returns only active goals', function () {
    $ssa = scopeSsa();
    $active = scopeGoal($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);
    scopeGoal($ssa, ['status' => SSAGoalStatus::MASTERED->value]);
    scopeGoal($ssa, ['status' => SSAGoalStatus::DISCONTINUED->value]);

    $result = SSAGoal::activeStatus()->pluck('id');

    expect($result)->toContain($active->id)
        ->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// forSsa scope
// ---------------------------------------------------------------------------

it('forSsa scope returns only goals belonging to the given SSA', function () {
    $ssaA = scopeSsa();
    $ssaB = scopeSsa();

    $goalA = scopeGoal($ssaA);
    scopeGoal($ssaB);

    $result = SSAGoal::forSsa($ssaA->id)->pluck('id');

    expect($result)->toContain($goalA->id)
        ->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// forStudent scope
// ---------------------------------------------------------------------------

it('forStudent scope returns only goals for the given student', function () {
    $ssaA = scopeSsa();
    $ssaB = scopeSsa(); // has a different student via factory

    $goalA = scopeGoal($ssaA);
    scopeGoal($ssaB);

    $result = SSAGoal::forStudent($ssaA->student_id)->pluck('id');

    expect($result)->toContain($goalA->id)
        ->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// orderForList scope
// ---------------------------------------------------------------------------

it('orderForList puts active goals before mastered and discontinued', function () {
    $ssa = scopeSsa();

    // Insert in reverse order so we can prove sorting, not insertion order
    $discontinued = scopeGoal($ssa, ['status' => SSAGoalStatus::DISCONTINUED->value]);
    $mastered = scopeGoal($ssa, ['status' => SSAGoalStatus::MASTERED->value]);
    $active = scopeGoal($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

    $ids = SSAGoal::forSsa($ssa->id)->orderForList()->pluck('id');

    expect($ids->first())->toBe($active->id)
        ->and($ids->last())->not->toBe($active->id);
});

it('orderForList orders goals within active group by created_at ascending', function () {
    $ssa = scopeSsa();

    $first = scopeGoal($ssa, [
        'status' => SSAGoalStatus::ACTIVE->value,
        'created_at' => now()->subMinutes(10),
    ]);
    $second = scopeGoal($ssa, [
        'status' => SSAGoalStatus::ACTIVE->value,
        'created_at' => now()->subMinutes(5),
    ]);
    $third = scopeGoal($ssa, [
        'status' => SSAGoalStatus::ACTIVE->value,
        'created_at' => now(),
    ]);

    $ids = SSAGoal::forSsa($ssa->id)->orderForList()->pluck('id')->values();

    expect($ids[0])->toBe($first->id)
        ->and($ids[1])->toBe($second->id)
        ->and($ids[2])->toBe($third->id);
});

it('orderForList places all active goals before any non-active goals', function () {
    $ssa = scopeSsa();

    $mastered = scopeGoal($ssa, [
        'status' => SSAGoalStatus::MASTERED->value,
        'created_at' => now()->subHour(), // older timestamp
    ]);
    $active = scopeGoal($ssa, [
        'status' => SSAGoalStatus::ACTIVE->value,
        'created_at' => now(), // newer timestamp
    ]);

    $ids = SSAGoal::forSsa($ssa->id)->orderForList()->pluck('id')->values();

    expect($ids[0])->toBe($active->id)
        ->and($ids[1])->toBe($mastered->id);
});

// ---------------------------------------------------------------------------
// Factory state helpers (smoke tests for the factory itself)
// ---------------------------------------------------------------------------

it('factory creates an active goal by default', function () {
    $goal = SSAGoal::factory()->create();

    expect($goal->status)->toBe(SSAGoalStatus::ACTIVE);
});

it('factory mastered() state sets status to mastered', function () {
    $goal = SSAGoal::factory()->mastered()->create();

    expect($goal->status)->toBe(SSAGoalStatus::MASTERED);
});

it('factory discontinued() state sets status to discontinued', function () {
    $goal = SSAGoal::factory()->discontinued()->create();

    expect($goal->status)->toBe(SSAGoalStatus::DISCONTINUED);
});
