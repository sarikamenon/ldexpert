<?php

declare(strict_types=1);

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

uses(DuskTestCase::class, DatabaseMigrations::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function therapistBrowserTherapist(): User
{
    return User::factory()->therapist()->create([
        'email' => 'therapist+goals-'.Str::uuid().'@example.com',
        'password' => bcrypt('Password123!'),
    ]);
}

function therapistBrowserSsa(User $therapist): ServiceSupportAgreement
{
    $student = User::factory()->student()->create(['name' => 'Browser Test Student']);
    $school = School::factory()->create();
    StudentProfile::factory()->create([
        'user_id' => $student->id,
        'school_id' => $school->id,
    ]);
    $service = Service::factory()->create(['name' => 'Speech Therapy']);

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
// Goals tab
// ---------------------------------------------------------------------------

it('allows therapist to add a goal from the Goals tab', function () {
    $therapist = therapistBrowserTherapist();
    $ssa = therapistBrowserSsa($therapist);

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
});

it('shows the goals card on the first session log for an SSA', function () {
    $therapist = therapistBrowserTherapist();
    $ssa = therapistBrowserSsa($therapist);

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
});

it('shows the previous session notes card after an approved log exists', function () {
    $therapist = therapistBrowserTherapist();
    $ssa = therapistBrowserSsa($therapist);

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
});

it('shows the Goals tab link on the therapist SSA show page', function () {
    $therapist = therapistBrowserTherapist();
    $ssa = therapistBrowserSsa($therapist);

    $this->browse(function (Browser $browser) use ($therapist, $ssa) {
        $browser->loginAs($therapist)
            ->visit(route('therapist.ssas.show', $ssa))
            ->assertSee('Goals');
    });
});
