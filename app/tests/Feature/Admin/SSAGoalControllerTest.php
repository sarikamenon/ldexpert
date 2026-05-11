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

function goalAdmin(): User
{
    return User::factory()->admin()->create();
}

function goalSsa(array $overrides = []): ServiceSupportAgreement
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $service = Service::factory()->create();

    return ServiceSupportAgreement::factory()->create(array_merge([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ], $overrides));
}

function goalForSsa(ServiceSupportAgreement $ssa, array $overrides = []): SSAGoal
{
    return SSAGoal::factory()->create(array_merge([
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// CREATE
// ---------------------------------------------------------------------------

it('allows admin to view the create goal page', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->get(route('admin.ssas.goals.create', $ssa))
        ->assertOk()
        ->assertSee('Add Goal');
});

it('creates a goal and redirects to goals tab', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $response = $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => '1.1',
            'objective' => 'Student will improve reading fluency to 90 wpm.',
            'progress' => null,
        ]);

    $response->assertRedirect(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
    $response->assertSessionHas('status', 'Goal added successfully.');

    $this->assertDatabaseHas('ssa_goals', [
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
        'number' => '1.1',
        'objective' => 'Student will improve reading fluency to 90 wpm.',
        'status' => SSAGoalStatus::ACTIVE->value,
    ]);
});

it('stores progress text when supplied', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => '2',
            'objective' => 'Student will use AAC device for 5 exchanges per session.',
            'progress' => '3 out of 5 target met consistently.',
        ]);

    $this->assertDatabaseHas('ssa_goals', [
        'ssa_id' => $ssa->id,
        'progress' => '3 out of 5 target met consistently.',
    ]);
});

// ---------------------------------------------------------------------------
// CREATE – validation failures
// ---------------------------------------------------------------------------

it('rejects store when number is missing', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => '',
            'objective' => 'Some objective text here.',
        ])
        ->assertSessionHasErrors('number');
});

it('rejects store when number exceeds 50 characters', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => str_repeat('x', 51),
            'objective' => 'Some objective text here.',
        ])
        ->assertSessionHasErrors('number');
});

it('rejects store when objective is missing', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => '',
        ])
        ->assertSessionHasErrors('objective');
});

it('rejects store when objective exceeds 5000 characters', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => str_repeat('a', 5001),
        ])
        ->assertSessionHasErrors('objective');
});

it('rejects store when progress exceeds 1000 characters', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();

    $this->actingAs($admin)
        ->post(route('admin.ssas.goals.store', $ssa), [
            'number' => '1',
            'objective' => 'Valid objective text.',
            'progress' => str_repeat('p', 1001),
        ])
        ->assertSessionHasErrors('progress');
});

// ---------------------------------------------------------------------------
// EDIT / UPDATE
// ---------------------------------------------------------------------------

it('allows admin to view the edit goal page', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa, ['number' => '3', 'objective' => 'Original objective.']);

    $this->actingAs($admin)
        ->get(route('admin.ssas.goals.edit', ['ssa' => $ssa, 'goal' => $goal]))
        ->assertOk()
        ->assertSee('Original objective.');
});

it('returns 404 when editing a goal that does not belong to the SSA', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $otherSsa = goalSsa();
    $goal = goalForSsa($otherSsa);

    $this->actingAs($admin)
        ->get(route('admin.ssas.goals.edit', ['ssa' => $ssa, 'goal' => $goal]))
        ->assertNotFound();
});

it('updates a goal and redirects to goals tab', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa, ['number' => '1', 'objective' => 'Old objective.']);

    $response = $this->actingAs($admin)
        ->put(route('admin.ssas.goals.update', ['ssa' => $ssa, 'goal' => $goal]), [
            'number' => '1a',
            'objective' => 'Updated objective text.',
            'progress' => 'Making good progress.',
        ]);

    $response->assertRedirect(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
    $response->assertSessionHas('status', 'Goal updated successfully.');

    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'number' => '1a',
        'objective' => 'Updated objective text.',
        'progress' => 'Making good progress.',
    ]);
});

it('rejects update when objective is missing', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa);

    $this->actingAs($admin)
        ->put(route('admin.ssas.goals.update', ['ssa' => $ssa, 'goal' => $goal]), [
            'number' => '1',
            'objective' => '',
        ])
        ->assertSessionHasErrors('objective');
});

// ---------------------------------------------------------------------------
// CHANGE STATUS
// ---------------------------------------------------------------------------

it('marks an active goal as mastered', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => SSAGoalStatus::MASTERED->value,
        ]);

    $response->assertRedirect(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
    $response->assertSessionHas('status', 'Goal status updated.');

    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'status' => SSAGoalStatus::MASTERED->value,
    ]);
});

it('marks an active goal as discontinued', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => SSAGoalStatus::DISCONTINUED->value,
        ]);

    $this->assertDatabaseHas('ssa_goals', [
        'id' => $goal->id,
        'status' => SSAGoalStatus::DISCONTINUED->value,
    ]);
});

it('rejects an invalid status value', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => 'active', // active is not allowed via the change-status endpoint
        ])
        ->assertSessionHasErrors('status');
});

it('rejects changing status to active via change-status endpoint', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $goal = goalForSsa($ssa, ['status' => SSAGoalStatus::MASTERED->value]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => 'active',
        ])
        ->assertSessionHasErrors('status');
});

it('returns 404 when changing status for a goal that does not belong to the SSA', function () {
    $admin = goalAdmin();
    $ssa = goalSsa();
    $otherSsa = goalSsa();
    $goal = goalForSsa($otherSsa);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
            'status' => SSAGoalStatus::MASTERED->value,
        ])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// AUTHORIZATION
// ---------------------------------------------------------------------------

it('denies access to non-admin users', function () {
    $ssa = goalSsa();
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get(route('admin.ssas.goals.create', $ssa))
        ->assertForbidden();
});
