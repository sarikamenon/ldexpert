<?php

declare(strict_types=1);

use App\Enums\SSAGoalStatus;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function therapistGoalTherapist(): User
{
    return User::factory()->therapist()->create();
}

function therapistGoalSsa(User $therapist, array $overrides = []): ServiceSupportAgreement
{
    $student = User::factory()->student()->create();
    $service = Service::factory()->create();

    return ServiceSupportAgreement::factory()->create(array_merge([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ], $overrides));
}

function therapistGoalForSsa(ServiceSupportAgreement $ssa, array $overrides = []): SSAGoal
{
    return SSAGoal::factory()->create(array_merge([
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// CREATE
// ---------------------------------------------------------------------------

it('allows assigned therapist to view the create goal page', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $this->actingAs($therapist)
        ->get(route('therapist.ssas.goals.create', $ssa))
        ->assertOk()
        ->assertSee('Add Goal');
});

it('returns 404 for unassigned therapist viewing the create goal page', function () {
    $therapist = therapistGoalTherapist();
    $other = therapistGoalTherapist();
    $ssa = therapistGoalSsa($other);

    // resolveRouteBinding scopes SSAs to the assigned therapist on therapist.* routes,
    // so an unassigned therapist gets a 404 before the policy even runs.
    $this->actingAs($therapist)
        ->get(route('therapist.ssas.goals.create', $ssa))
        ->assertNotFound();
});

it('allows assigned therapist to create a goal', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $response = $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => 'Student will improve articulation accuracy to 80%.',
        ]);

    $response->assertRedirect(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
    $response->assertSessionHas('status', 'Goal added successfully.');

    $this->assertDatabaseHas('ssa_goals', [
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
        'number' => '1',
        'status' => SSAGoalStatus::ACTIVE->value,
    ]);
});

it('returns 404 when unassigned therapist tries to create a goal', function () {
    $therapist = therapistGoalTherapist();
    $other = therapistGoalTherapist();
    $ssa = therapistGoalSsa($other);

    // resolveRouteBinding returns 404 for SSAs not assigned to this therapist.
    $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => 'Unauthorized goal attempt.',
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('ssa_goals', ['ssa_id' => $ssa->id]);
});

// ---------------------------------------------------------------------------
// CREATE – validation
// ---------------------------------------------------------------------------

it('rejects store when number is missing', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => '',
            'objective' => 'Valid objective text.',
        ])
        ->assertSessionHasErrors('number');
});

it('rejects store when objective is missing', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => '',
        ])
        ->assertSessionHasErrors('objective');
});

it('rejects store when number exceeds 50 characters', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => str_repeat('n', 51),
            'objective' => 'Valid objective.',
        ])
        ->assertSessionHasErrors('number');
});

it('rejects store when objective exceeds 5000 characters', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => str_repeat('o', 5001),
        ])
        ->assertSessionHasErrors('objective');
});

it('rejects store when progress exceeds 1000 characters', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);

    $this->actingAs($therapist)
        ->post(route('therapist.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => 'Valid objective.',
            'progress' => str_repeat('p', 1001),
        ])
        ->assertSessionHasErrors('progress');
});

// ---------------------------------------------------------------------------
// EDIT / UPDATE
// ---------------------------------------------------------------------------

it('allows assigned therapist to view the edit goal page', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($ssa, ['objective' => 'Original text here.']);

    $this->actingAs($therapist)
        ->get(route('therapist.ssas.goals.edit', ['ssa' => $ssa, 'goal' => $goal]))
        ->assertOk()
        ->assertSee('Original text here.');
});

it('returns 404 for unassigned therapist viewing the edit goal page', function () {
    $therapist = therapistGoalTherapist();
    $other = therapistGoalTherapist();
    $ssa = therapistGoalSsa($other);
    $goal = therapistGoalForSsa($ssa);

    // resolveRouteBinding returns 404 for SSAs not assigned to this therapist.
    $this->actingAs($therapist)
        ->get(route('therapist.ssas.goals.edit', ['ssa' => $ssa, 'goal' => $goal]))
        ->assertNotFound();
});

it('allows assigned therapist to update a goal', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($ssa, ['number' => '1', 'objective' => 'Old text.']);

    $response = $this->actingAs($therapist)
        ->put(route('therapist.ssas.goals.update', ['ssa' => $ssa, 'goal' => $goal]), [
            'number' => '1b',
            'objective' => 'Revised objective text.',
            'progress' => '70% accuracy achieved.',
        ]);

    $response->assertRedirect(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
    $response->assertSessionHas('status', 'Goal updated successfully.');

    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'number' => '1b',
        'objective' => 'Revised objective text.',
        'progress' => '70% accuracy achieved.',
    ]);
});

it('returns 404 when goal does not belong to the SSA on update', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $otherSsa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($otherSsa);

    $this->actingAs($therapist)
        ->put(route('therapist.ssas.goals.update', ['ssa' => $ssa, 'goal' => $goal]), [
            'number' => '1',
            'objective' => 'Some objective.',
        ])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// CHANGE STATUS
// ---------------------------------------------------------------------------

it('allows assigned therapist to mark a goal as mastered', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

    $response = $this->actingAs($therapist)
        ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => SSAGoalStatus::MASTERED->value,
        ]);

    $response->assertRedirect(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'status' => SSAGoalStatus::MASTERED->value,
    ]);
});

it('allows assigned therapist to mark a goal as discontinued', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

    $this->actingAs($therapist)
        ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => SSAGoalStatus::DISCONTINUED->value,
        ]);

    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'status' => SSAGoalStatus::DISCONTINUED->value,
    ]);
});

it('returns 404 when unassigned therapist tries to change goal status', function () {
    $therapist = therapistGoalTherapist();
    $other = therapistGoalTherapist();
    $ssa = therapistGoalSsa($other);
    $goal = therapistGoalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

    // resolveRouteBinding returns 404 for SSAs not assigned to this therapist.
    $this->actingAs($therapist)
        ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => SSAGoalStatus::MASTERED->value,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'status' => SSAGoalStatus::ACTIVE->value,
    ]);
});

it('rejects change-status with an invalid status value', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($ssa);

    $this->actingAs($therapist)
        ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => 'active',
        ])
        ->assertSessionHasErrors('status');
});

it('rejects change-status when status is missing', function () {
    $therapist = therapistGoalTherapist();
    $ssa = therapistGoalSsa($therapist);
    $goal = therapistGoalForSsa($ssa);

    $this->actingAs($therapist)
        ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [])
        ->assertSessionHasErrors('status');
});
