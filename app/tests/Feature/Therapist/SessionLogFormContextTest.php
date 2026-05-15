<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SSAGoalStatus;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\SSAGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that the session log create/edit form injects the correct context:
 * - "goals" mode (first log) → shows active SSA goals
 * - "previous_notes" mode (subsequent log) → shows most-recent approved/submitted notes
 * - Draft and cancelled logs do NOT count as prior logs.
 * - The current log is excluded from the prior-existence check on edit.
 */
final class SessionLogFormContextTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeTherapist(): User
    {
        return User::factory()->therapist()->create();
    }

    private function makeSsaForTherapist(User $therapist): ServiceSupportAgreement
    {
        $student = User::factory()->student()->create();
        $service = Service::factory()->create();

        // The SSA factory's afterCreating() already syncs primary_service_id into ssa_services.
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
        ]);

        return $ssa;
    }

    private function makeGoalForSsa(ServiceSupportAgreement $ssa, array $overrides = []): SSAGoal
    {
        return SSAGoal::factory()->create(array_merge([
            'ssa_id' => $ssa->id,
            'student_id' => $ssa->student_id,
            'status' => SSAGoalStatus::ACTIVE->value,
            'objective' => 'Student will demonstrate progress in articulation.',
        ], $overrides));
    }

    private function makeSessionLogForSsa(ServiceSupportAgreement $ssa, string $statusState, array $overrides = []): SessionLog
    {
        /** @var SessionLog $log */
        $log = SessionLog::factory()
            ->$statusState()
            ->create(array_merge([
                'therapist_id' => $ssa->assigned_therapist_id,
                'student_id' => $ssa->student_id,
                'ssa_id' => $ssa->id,
                'service_id' => $ssa->primary_service_id,
                'notes' => 'Notes from the previous session log.',
            ], $overrides));

        return $log;
    }

    // ---------------------------------------------------------------------------
    // Goals mode (first session log)
    // ---------------------------------------------------------------------------

    public function test_create_page_shows_goals_card_when_no_prior_submitted_or_approved_log_exists(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $this->makeGoalForSsa($ssa, ['objective' => 'Unique objective text for detection.']);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('Goals for this SSA');
        $response->assertSee('Unique objective text for detection.');
    }

    public function test_active_goals_appear_on_first_session_log_create_page(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $this->makeGoalForSsa($ssa, [
            'status' => SSAGoalStatus::ACTIVE->value,
            'objective' => 'Active goal objective.',
        ]);
        $this->makeGoalForSsa($ssa, [
            'status' => SSAGoalStatus::MASTERED->value,
            'objective' => 'Mastered goal objective.',
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('Active goal objective.');
        // Mastered goals are not shown in the goals card (only active are fetched)
        $response->assertDontSee('Mastered goal objective.');
    }

    public function test_create_page_shows_no_active_goals_message_when_ssa_has_no_active_goals(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        // No goals created — SSA has none
        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('No active goals are recorded for this SSA');
    }

    // ---------------------------------------------------------------------------
    // Draft / cancelled logs do NOT count as prior logs
    // ---------------------------------------------------------------------------

    public function test_create_page_still_shows_goals_when_only_a_draft_log_exists(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $this->makeGoalForSsa($ssa, ['objective' => 'Should still show goals.']);

        // A draft exists — but drafts don't count as "prior submitted/approved"
        $this->makeSessionLogForSsa($ssa, 'draft');

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('Goals for this SSA');
        $response->assertSee('Should still show goals.');
    }

    public function test_create_page_still_shows_goals_when_only_a_cancelled_log_exists(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $this->makeGoalForSsa($ssa, ['objective' => 'Goals visible after cancelled log.']);

        // A cancelled log — should not count either
        $this->makeSessionLogForSsa($ssa, 'cancelled');

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('Goals for this SSA');
    }

    // ---------------------------------------------------------------------------
    // Previous notes mode (subsequent session log)
    // ---------------------------------------------------------------------------

    public function test_create_page_shows_previous_notes_when_a_submitted_log_exists(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->makeSessionLogForSsa($ssa, 'submitted', [
            'notes' => 'These are the notes from the submitted session.',
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('Previous Session Notes');
        $response->assertSee('These are the notes from the submitted session.');
    }

    public function test_create_page_shows_previous_notes_when_an_approved_log_exists(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        $this->makeSessionLogForSsa($ssa, 'approved', [
            'notes' => 'Approved session notes to display.',
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]));

        $response->assertOk();
        $response->assertSee('Previous Session Notes');
        $response->assertSee('Approved session notes to display.');
    }

    // ---------------------------------------------------------------------------
    // Edit: current draft is excluded from the prior-existence check
    // ---------------------------------------------------------------------------

    public function test_edit_of_draft_shows_goals_card_not_previous_notes(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);
        $this->makeGoalForSsa($ssa, ['objective' => 'Goal visible during draft edit.']);

        // The draft being edited is the only log — it must not count against itself
        $draft = $this->makeSessionLogForSsa($ssa, 'draft');

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.edit', $draft));

        $response->assertOk();
        $response->assertSee('Goals for this SSA');
        $response->assertSee('Goal visible during draft edit.');
    }

    public function test_edit_page_shows_previous_notes_when_a_prior_approved_log_exists(): void
    {
        $therapist = $this->makeTherapist();
        $ssa = $this->makeSsaForTherapist($therapist);

        // An approved log came first
        $this->makeSessionLogForSsa($ssa, 'approved', [
            'notes' => 'Earlier approved notes shown on edit.',
        ]);

        // The log being edited is a draft (the therapist started a second one)
        $draft = $this->makeSessionLogForSsa($ssa, 'draft');

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.edit', $draft));

        $response->assertOk();
        $response->assertSee('Previous Session Notes');
        $response->assertSee('Earlier approved notes shown on edit.');
    }

    // ---------------------------------------------------------------------------
    // No SSA selected → no context card rendered
    // ---------------------------------------------------------------------------

    public function test_create_page_without_ssa_shows_no_context_card(): void
    {
        $therapist = $this->makeTherapist();

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.create'));

        $response->assertOk();
        $response->assertDontSee('Goals for this SSA');
        $response->assertDontSee('Previous Session Notes');
    }

}
