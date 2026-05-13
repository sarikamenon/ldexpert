<?php

declare(strict_types=1);

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

uses(DuskTestCase::class, DatabaseMigrations::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function adminGoalAdmin(): User
{
    return User::factory()->admin()->create([
        'email' => 'admin+goals-'.Str::uuid().'@example.com',
        'password' => bcrypt('Password123!'),
    ]);
}

function adminGoalSsa(): ServiceSupportAgreement
{
    $student = User::factory()->student()->create(['name' => 'Goals Test Student']);
    $service = Service::factory()->create(['name' => 'Speech Therapy']);
    $therapist = User::factory()->therapist()->create();

    return ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addYear(),
    ]);
}

// ---------------------------------------------------------------------------
// Add Goal flow
// ---------------------------------------------------------------------------

it('allows admin to add a goal from the Goals tab', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();

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
});

// ---------------------------------------------------------------------------
// Edit Goal flow
// ---------------------------------------------------------------------------

it('allows admin to edit an existing goal', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();

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
});

// ---------------------------------------------------------------------------
// Mark Mastered flow
// ---------------------------------------------------------------------------

it('allows admin to mark a goal as mastered', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();

    SSAGoal::factory()->create([
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

        // Intercept the SweetAlert2 confirm to auto-confirm, then click the button.
        $browser->script('
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
        ');

        $browser->click('[data-status="mastered"]')
            ->pause(1500)
            ->assertSee('Goal marked as mastered.');

        $browser->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
            ->assertSee('Mastered')
            ->assertSee('Goal to be mastered.');
    });
});

// ---------------------------------------------------------------------------
// Goals tab visibility
// ---------------------------------------------------------------------------

it('shows the Goals tab on the SSA show page', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();

    $this->browse(function (Browser $browser) use ($admin, $ssa) {
        $browser->loginAs($admin)
            ->visit(route('admin.ssas.show', $ssa))
            ->assertSee('Goals');
    });
});

it('shows the empty state on the Goals tab when no goals exist', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();

    $this->browse(function (Browser $browser) use ($admin, $ssa) {
        $browser->loginAs($admin)
            ->visit(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']))
            ->assertSee('No goals yet');
    });
});

it('shows per-SSA add goal and per-goal actions on the student Goals tab', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();
    /** @var User $student */
    $student = User::query()->findOrFail($ssa->student_id);

    SSAGoal::factory()->create([
        'ssa_id' => $ssa->id,
        'student_id' => $ssa->student_id,
        'number' => '9',
        'objective' => 'Goal visible on student tab.',
        'status' => SSAGoalStatus::ACTIVE->value,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $student, $ssa) {
        $browser->loginAs($admin)
            ->visit(route('admin.students.show', ['student' => $student, 'tab' => 'goals']))
            ->assertSee('Goal visible on student tab.')
            ->assertSee('+ Add Goal')
            ->assertSee('Edit')
            ->assertSee('Mark Mastered')
            ->clickLink('Edit')
            ->assertPathIs("/admin/ssas/{$ssa->id}/goals/".SSAGoal::query()->where('ssa_id', $ssa->id)->where('number', '9')->value('id').'/edit');
    });
});

it('lists SSAs with add goal when student has no goals yet', function () {
    $admin = adminGoalAdmin();
    $ssa = adminGoalSsa();
    /** @var User $student */
    $student = User::query()->findOrFail($ssa->student_id);

    $this->browse(function (Browser $browser) use ($admin, $student) {
        $browser->loginAs($admin)
            ->visit(route('admin.students.show', ['student' => $student, 'tab' => 'goals']))
            ->assertSee('No goals for this SSA yet.')
            ->assertSee('+ Add Goal');
    });
});
