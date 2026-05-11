<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\SessionLogStatus;
use App\Enums\SSAGoalStatus;
use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\SSAGoal;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistSSAGoalsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const PASSWORD = 'Password123!';

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createTherapist(): User
    {
        return User::factory()->therapist()->create([
            'email' => 'therapist+goals-' . Str::uuid() . '@example.com',
            'password' => bcrypt(self::PASSWORD),
        ]);
    }

    private function createSsaForTherapist(User $therapist): ServiceSupportAgreement
    {
        $student = User::factory()->student()->create(['name' => 'Browser Test Student']);
        $school = School::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create(['name' => 'Speech Therapy']);

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

    // ---------------------------------------------------------------------------
    // Goals tab
    // ---------------------------------------------------------------------------

    /** Therapist sees the Goals tab on their SSA detail page and can add a goal. */
    public function test_therapist_can_add_goal_from_goals_tab(): void
    {
        $therapist = $this->createTherapist();
        $ssa = $this->createSsaForTherapist($therapist);

        $this->browse(function (Browser $browser) use ($therapist, $ssa) {
            $browser->loginAs($therapist)
                ->visit(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
                ->assertSee('Goals')
                ->clickLink('Add Goal')
                ->assertPathIs("/therapist/ssas/{$ssa->id}/goals/create")
                ->assertSee('Add Goal')
                ->type('number', '1')
                ->type('objective', 'Therapist-entered goal for this SSA.')
                ->press('Save Goal')
                ->assertSee('Goal added successfully.')
                ->assertSee('Therapist-entered goal for this SSA.');
        });
    }

    /** Therapist sees the Goals card on the session log create form when no prior submitted/approved log exists. */
    public function test_therapist_sees_goals_card_on_first_session_log(): void
    {
        $therapist = $this->createTherapist();
        $ssa = $this->createSsaForTherapist($therapist);

        SSAGoal::factory()->create([
            'ssa_id' => $ssa->id,
            'student_id' => $ssa->student_id,
            'number' => '1',
            'objective' => 'Visible goal on first session log.',
            'status' => SSAGoalStatus::ACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) use ($therapist, $ssa) {
            $browser->loginAs($therapist)
                ->visit(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]))
                ->assertSee('Goals for this SSA')
                ->assertSee('Visible goal on first session log.');
        });
    }

    /** Therapist sees the Previous Session Notes card after a submitted/approved log exists. */
    public function test_therapist_sees_previous_session_notes_card_after_approved_log(): void
    {
        $therapist = $this->createTherapist();
        $ssa = $this->createSsaForTherapist($therapist);

        $start = CarbonImmutable::now('UTC')->subDays(5)->setTime(10, 0);

        SessionLog::factory()->approved()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $ssa->student_id,
            'ssa_id' => $ssa->id,
            'service_id' => $ssa->primary_service_id,
            'session_date' => $start->toDateString(),
            'start_time' => $start->toDateTimeString(),
            'end_time' => $start->addMinutes(60)->toDateTimeString(),
            'notes' => 'Prior approved session notes visible here.',
            'status' => SessionLogStatus::APPROVED,
        ]);

        $this->browse(function (Browser $browser) use ($therapist, $ssa) {
            $browser->loginAs($therapist)
                ->visit(route('therapist.session-logs.create', ['ssa_id' => $ssa->id]))
                ->assertSee('Previous Session Notes')
                ->assertSee('Prior approved session notes visible here.');
        });
    }

    /** Goals tab is accessible and the Goals tab link is visible on SSA show page. */
    public function test_therapist_goals_tab_is_present_on_ssa_show_page(): void
    {
        $therapist = $this->createTherapist();
        $ssa = $this->createSsaForTherapist($therapist);

        $this->browse(function (Browser $browser) use ($therapist, $ssa) {
            $browser->loginAs($therapist)
                ->visit(route('therapist.ssas.show', $ssa))
                ->assertSee('Goals');
        });
    }
}
