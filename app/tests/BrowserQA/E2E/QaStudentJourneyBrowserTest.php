<?php

declare(strict_types=1);

use App\Enums\SSAGoalStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\SSAGoal;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Student Journey ──────────────────────────────────────────────────────────

it('TC-E001 full chain: admin creates setup, therapist logs session, student sees it', function (): void {
    // Arrange — full chain via TD-E001
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = School::factory()->qa()->create([
        'status'   => SchoolStatus::ACTIVE,
        'timezone' => 'America/New_York',
    ]);

    $therapist = User::factory()->therapist()->qa()->create(['email' => 'qa.therapist@e2e-test.com']);
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
        'timezone'   => 'America/New_York',
    ]);

    $student = User::factory()->student()->qa()->create(['email' => 'qa.student@e2e-test.com']);
    StudentProfile::factory()->create([
        'user_id'    => $student->id,
        'school_id'  => $school->id,
        'first_name' => 'E2E',
        'last_name'  => 'Student',
        'timezone'   => 'America/New_York',
    ]);

    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    // NOTE: service_support_agreements has no school_id column — the SSA is
    // linked to the school via the student, not directly.
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    SSAGoal::factory()->create([
        'ssa_id'     => $ssa->id,
        'student_id' => $student->id,
        'status'     => SSAGoalStatus::ACTIVE,
        'objective'  => 'Improve reading comprehension to 80%',
        'goal'       => 'Reading comprehension goal',
    ]);

    $schedule = Schedule::factory()->create([
        'student_id'    => $student->id,
        'therapist_id'  => $therapist->id,
        'ssa_id'        => $ssa->id,
        'service_id'    => $service->id,
        'school_id'     => $school->id,
        'schedule_date' => now()->addDays(3)->toDateString(),
        'start_time'    => now()->addDays(3)->setTime(9, 0)->utc(),
        'status'        => ScheduleStatus::SCHEDULED,
    ]);

    $sessionLog = SessionLog::factory()->approved()->create([
        'student_id'      => $student->id,
        'therapist_id'    => $therapist->id,
        'ssa_id'          => $ssa->id,
        'school_id'       => $school->id,
        'service_id'      => $service->id,
        'approved_by_id'  => $admin->id,
        'submitted_by_id' => $therapist->id,
    ]);

    // Assert — student sees their data (dashboard renders without errors)
    $this->browse(function (Browser $browser) use ($student): void {
        $browser->loginAs($student)
            ->visit('/student/dashboard')
            ->waitForText('Welcome')
            ->assertDontSee('Whoops')
            ->assertDontSee('Server Error');
    });

    $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    $this->assertDatabaseHas('session_logs', ['id' => $sessionLog->id, 'status' => 'approved']);
});

it('TC-E002 student sees approved session log in history after admin approval', function (): void {
    $this->markTestSkipped(
        'Not implemented: there is no student-facing session-history page. The only student route is '
        . '/student/dashboard (see routes/student.php). /student/session-history returns 404, so there is '
        . 'no surface on which a student can view their approved session logs.'
    );
});

it('TC-E003 student cannot see unapproved session log in history', function (): void {
    $this->markTestSkipped(
        'Not implemented: there is no student-facing session-history page. /student/session-history returns '
        . '404, so "student cannot see an unapproved log in history" has no real surface to assert against '
        . '(the original assertion passed only because the 404 page never shows the log id).'
    );
});

it('TC-E004 student A cannot see student B data across the full journey', function (): void {
    $admin  = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $school = School::factory()->qa()->create(['status' => SchoolStatus::ACTIVE]);

    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $studentA = User::factory()->student()->qa()->create();
    StudentProfile::factory()->create(['user_id' => $studentA->id, 'school_id' => $school->id]);

    $studentB = User::factory()->student()->qa()->create();
    StudentProfile::factory()->create([
        'user_id'    => $studentB->id,
        'school_id'  => $school->id,
        'first_name' => 'SecretStudentB',
        'last_name'  => 'Hidden',
    ]);

    $service = Service::factory()->create();
    $ssaB    = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $studentB->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    SessionLog::factory()->approved()->create([
        'student_id'      => $studentB->id,
        'therapist_id'    => $therapist->id,
        'ssa_id'          => $ssaB->id,
        'school_id'       => $school->id,
        'service_id'      => $service->id,
        'approved_by_id'  => $admin->id,
        'submitted_by_id' => $therapist->id,
    ]);

    // Only /student/dashboard exists as a student surface, so the isolation
    // check is performed there. (session-history / goals pages are not built.)
    $this->browse(function (Browser $browser) use ($studentA): void {
        $browser->loginAs($studentA)
            ->visit('/student/dashboard')
            ->waitForText('Welcome')
            ->assertDontSee('SecretStudentB');
    });
});

it('TC-E005 full flow with minimal data — no goals no schedules — loads without errors', function (): void {
    $admin  = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $school = School::factory()->qa()->create(['status' => SchoolStatus::ACTIVE]);

    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $student = User::factory()->student()->qa()->create();
    StudentProfile::factory()->create(['user_id' => $student->id, 'school_id' => $school->id]);

    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    // Only /student/dashboard exists as a student surface (no /student/goals
    // page is built), so the minimal-data smoke check runs against the dashboard.
    $this->browse(function (Browser $browser) use ($student): void {
        $browser->loginAs($student)
            ->visit('/student/dashboard')
            ->waitForText('Welcome')
            ->assertDontSee('Whoops')
            ->assertDontSee('Server Error');
    });
});
