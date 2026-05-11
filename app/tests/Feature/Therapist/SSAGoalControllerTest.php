<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SSAGoalStatus;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SSAGoalControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeTherapist(): User
    {
        return User::factory()->therapist()->create();
    }

    private function makeSsaForTherapist(User $therapist, array $overrides = []): ServiceSupportAgreement
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

    private function makeGoalForSsa(ServiceSupportAgreement $ssa, array $overrides = []): SSAGoal
    {
        return SSAGoal::factory()->create(array_merge([
            'ssa_id' => $ssa->id,
            'student_id' => $ssa->student_id,
        ], $overrides));
    }

    // ---------------------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------------------

    public function test_assigned_therapist_can_view_create_goal_page(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->actingAs($therapist)
            ->get(route('therapist.ssas.goals.create', $ssa))
            ->assertOk()
            ->assertSee('Add Goal');
    }

    public function test_unassigned_therapist_cannot_view_create_goal_page(): void
    {
        $therapist = $this->makeTherapist();
        $other = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($other);

        // resolveRouteBinding scopes SSAs to the assigned therapist on therapist.* routes,
        // so an unassigned therapist gets a 404 before the policy even runs.
        $this->actingAs($therapist)
            ->get(route('therapist.ssas.goals.create', $ssa))
            ->assertNotFound();
    }

    public function test_assigned_therapist_can_create_goal(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

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
    }

    public function test_unassigned_therapist_cannot_create_goal(): void
    {
        $therapist = $this->makeTherapist();
        $other = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($other);

        // resolveRouteBinding returns 404 for SSAs not assigned to this therapist.
        $this->actingAs($therapist)
            ->post(route('therapist.ssas.goals.store', $ssa), [
                'number' => '1',
                'objective' => 'Unauthorized goal attempt.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('ssa_goals', ['ssa_id' => $ssa->id]);
    }

    // ---------------------------------------------------------------------------
    // CREATE – validation
    // ---------------------------------------------------------------------------

    public function test_store_rejects_missing_number(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->actingAs($therapist)
            ->post(route('therapist.ssas.goals.store', $ssa), [
                'number' => '',
                'objective' => 'Valid objective text.',
            ])
            ->assertSessionHasErrors('number');
    }

    public function test_store_rejects_missing_objective(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->actingAs($therapist)
            ->post(route('therapist.ssas.goals.store', $ssa), [
                'number' => '1',
                'objective' => '',
            ])
            ->assertSessionHasErrors('objective');
    }

    public function test_store_rejects_number_over_50_chars(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->actingAs($therapist)
            ->post(route('therapist.ssas.goals.store', $ssa), [
                'number' => str_repeat('n', 51),
                'objective' => 'Valid objective.',
            ])
            ->assertSessionHasErrors('number');
    }

    public function test_store_rejects_objective_over_5000_chars(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->actingAs($therapist)
            ->post(route('therapist.ssas.goals.store', $ssa), [
                'number' => '1',
                'objective' => str_repeat('o', 5001),
            ])
            ->assertSessionHasErrors('objective');
    }

    public function test_store_rejects_progress_over_1000_chars(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->actingAs($therapist)
            ->post(route('therapist.ssas.goals.store', $ssa), [
                'number' => '1',
                'objective' => 'Valid objective.',
                'progress' => str_repeat('p', 1001),
            ])
            ->assertSessionHasErrors('progress');
    }

    // ---------------------------------------------------------------------------
    // EDIT / UPDATE
    // ---------------------------------------------------------------------------

    public function test_assigned_therapist_can_view_edit_goal_page(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($ssa, ['objective' => 'Original text here.']);

        $this->actingAs($therapist)
            ->get(route('therapist.ssas.goals.edit', ['ssa' => $ssa, 'goal' => $goal]))
            ->assertOk()
            ->assertSee('Original text here.');
    }

    public function test_unassigned_therapist_cannot_view_edit_goal_page(): void
    {
        $therapist = $this->makeTherapist();
        $other = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($other);
        $goal = $this->makeGoalForSsa($ssa);

        // resolveRouteBinding returns 404 for SSAs not assigned to this therapist.
        $this->actingAs($therapist)
            ->get(route('therapist.ssas.goals.edit', ['ssa' => $ssa, 'goal' => $goal]))
            ->assertNotFound();
    }

    public function test_assigned_therapist_can_update_goal(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($ssa, ['number' => '1', 'objective' => 'Old text.']);

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
    }

    public function test_returns_404_when_goal_does_not_belong_to_ssa_on_update(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $otherSsa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($otherSsa);

        $this->actingAs($therapist)
            ->put(route('therapist.ssas.goals.update', ['ssa' => $ssa, 'goal' => $goal]), [
                'number' => '1',
                'objective' => 'Some objective.',
            ])
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------------
    // CHANGE STATUS
    // ---------------------------------------------------------------------------

    public function test_assigned_therapist_can_mark_goal_as_mastered(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

        $response = $this->actingAs($therapist)
            ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
                'status' => SSAGoalStatus::MASTERED->value,
            ]);

        $response->assertRedirect(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']));
        $this->assertDatabaseHas('ssa_goals', [
            'id' => $goal->id,
            'status' => SSAGoalStatus::MASTERED->value,
        ]);
    }

    public function test_assigned_therapist_can_mark_goal_as_discontinued(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

        $this->actingAs($therapist)
            ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
                'status' => SSAGoalStatus::DISCONTINUED->value,
            ]);

        $this->assertDatabaseHas('ssa_goals', [
            'id' => $goal->id,
            'status' => SSAGoalStatus::DISCONTINUED->value,
        ]);
    }

    public function test_unassigned_therapist_cannot_change_goal_status(): void
    {
        $therapist = $this->makeTherapist();
        $other = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($other);
        $goal = $this->makeGoalForSsa($ssa, ['status' => SSAGoalStatus::ACTIVE->value]);

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
    }

    public function test_change_status_rejects_invalid_status_value(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($ssa);

        $this->actingAs($therapist)
            ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [
                'status' => 'active',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_change_status_rejects_missing_status(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $goal = $this->makeGoalForSsa($ssa);

        $this->actingAs($therapist)
            ->patch(route('therapist.ssas.goals.change-status', ['ssa' => $ssa, 'goal' => $goal]), [])
            ->assertSessionHasErrors('status');
    }
}
