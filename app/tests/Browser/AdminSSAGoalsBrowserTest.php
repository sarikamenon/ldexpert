<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\SSAGoalStatus;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class AdminSSAGoalsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const PASSWORD = 'Password123!';

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'email' => 'admin+goals-' . Str::uuid() . '@example.com',
            'password' => bcrypt(self::PASSWORD),
        ]);
    }

    private function createSsa(): ServiceSupportAgreement
    {
        $student = User::factory()->student()->create(['name' => 'Goals Test Student']);
        $service = Service::factory()->create(['name' => 'Speech Therapy']);
        $therapist = User::factory()->therapist()->create();

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
    // Add Goal flow
    // ---------------------------------------------------------------------------

    /** Admin clicks Add Goal → fills the create form → goal appears on the Goals tab. */
    public function test_admin_can_add_goal_from_goals_tab(): void
    {
        $admin = $this->createAdmin();
        $ssa = $this->createSsa();

        $this->browse(function (Browser $browser) use ($admin, $ssa) {
            $browser->loginAs($admin)
                ->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
                ->assertSee('Goals')
                ->clickLink('Add Goal')
                ->assertPathIs("/admin/ssas/{$ssa->id}/goals/create")
                ->assertSee('Add Goal')
                ->type('number', '1.1')
                ->type('objective', 'Student will demonstrate reading fluency at 90 wpm.')
                ->press('Save Goal')
                ->assertPathIs("/admin/ssas/{$ssa->id}")
                ->assertSee('Goal added successfully.')
                ->assertSee('1.1')
                ->assertSee('Student will demonstrate reading fluency at 90 wpm.');
        });
    }

    // ---------------------------------------------------------------------------
    // Edit Goal flow
    // ---------------------------------------------------------------------------

    /** Admin clicks Edit on an existing goal → updates → sees updated text on tab. */
    public function test_admin_can_edit_an_existing_goal(): void
    {
        $admin = $this->createAdmin();
        $ssa = $this->createSsa();

        $goal = SSAGoal::factory()->create([
            'ssa_id' => $ssa->id,
            'student_id' => $ssa->student_id,
            'number' => '2',
            'objective' => 'Original objective text.',
            'status' => SSAGoalStatus::ACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $ssa, $goal) {
            $browser->loginAs($admin)
                ->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
                ->assertSee('Original objective text.')
                ->clickLink('Edit')
                ->assertPathIs("/admin/ssas/{$ssa->id}/goals/{$goal->id}/edit")
                ->clear('objective')
                ->type('objective', 'Updated objective text after edit.')
                ->press('Save Goal')
                ->assertSee('Goal updated successfully.')
                ->assertSee('Updated objective text after edit.');
        });
    }

    // ---------------------------------------------------------------------------
    // Mark Mastered flow
    // ---------------------------------------------------------------------------

    /**
     * Admin marks an active goal as mastered.
     * The SweetAlert2 confirm dialog is dismissed via JS injection to avoid
     * interacting with the dialog directly, which is unreliable in headless mode.
     */
    public function test_admin_can_mark_goal_as_mastered(): void
    {
        $admin = $this->createAdmin();
        $ssa = $this->createSsa();

        $goal = SSAGoal::factory()->create([
            'ssa_id' => $ssa->id,
            'student_id' => $ssa->student_id,
            'number' => '3',
            'objective' => 'Goal to be mastered.',
            'status' => SSAGoalStatus::ACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $ssa) {
            $browser->loginAs($admin)
                ->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
                ->assertSee('Goal to be mastered.')
                ->assertSee('Mark Mastered');

            // Intercept the SweetAlert2 confirm to auto-confirm, then click the button
            $browser->script("
                window._swalConfirmAll = true;
                const orig = window.Swal ? window.Swal.fire.bind(window.Swal) : null;
                if (orig) {
                    window.Swal.fire = function(opts) {
                        if (window._swalConfirmAll) {
                            return Promise.resolve({ isConfirmed: true, value: true });
                        }
                        return orig(opts);
                    };
                }
            ");

            $browser->click('[data-status="mastered"]')
                ->pause(1500)
                ->assertSee('Goal marked as mastered.');

            // After reload the goal should appear under the Mastered group label
            $browser->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
                ->assertSee('Mastered')
                ->assertSee('Goal to be mastered.');
        });
    }

    // ---------------------------------------------------------------------------
    // Goals tab is visible on the SSA show page
    // ---------------------------------------------------------------------------

    public function test_goals_tab_is_visible_on_ssa_show_page(): void
    {
        $admin = $this->createAdmin();
        $ssa = $this->createSsa();

        $this->browse(function (Browser $browser) use ($admin, $ssa) {
            $browser->loginAs($admin)
                ->visit(route('admin.ssas.show', $ssa))
                ->assertSee('Goals');
        });
    }

    public function test_empty_goals_tab_shows_empty_state(): void
    {
        $admin = $this->createAdmin();
        $ssa = $this->createSsa();

        $this->browse(function (Browser $browser) use ($admin, $ssa) {
            $browser->loginAs($admin)
                ->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
                ->assertSee('No goals yet');
        });
    }
}
