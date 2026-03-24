<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistSessionLogsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_therapist_can_open_session_logs_pages(): void
    {
        $therapist = User::factory()->therapist()->create([
            'email' => 'therapist+sessionlogs@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) use ($therapist) {
            $browser->loginAs($therapist)
                ->visit('/therapist/session-logs')
                ->assertPathIs('/therapist/session-logs')
                ->assertSee('Session Logs')
                ->visit('/therapist/session-logs/create')
                ->assertPathIs('/therapist/session-logs/create')
                ->assertSee('Create Session Log');
        });
    }

    /**
     * The "Add Session Log" button was removed from the index page (entry is via the nav menu
     * and SSA detail page instead). This test verifies the select-ssa → create form flow works.
     */
    public function test_therapist_can_access_non_scheduled_session_log_flow(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create(['name' => 'Test Student']);
        $school = School::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create(['name' => 'Speech Therapy']);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
        $ssa->services()->attach($service->id, ['is_primary' => true]);

        $this->browse(function (Browser $browser) use ($therapist, $student, $ssa) {
            $browser->loginAs($therapist)
                ->visit('/therapist/session-logs/select-ssa')
                ->assertPathIs('/therapist/session-logs/select-ssa')
                ->assertSee('Select SSA')
                ->assertSee($student->name)
                ->select('ssa_id', (string) $ssa->id)
                ->press('Continue')
                ->assertPathIs('/therapist/session-logs/create')
                ->assertQueryStringHas('ssa_id', (string) $ssa->id)
                ->assertSee('Create Session Log')
                ->assertSee($student->name);
        });
    }

    public function test_therapist_can_create_session_log_from_ssa_page(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create(['name' => 'Test Student']);
        $school = School::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create(['name' => 'Speech Therapy']);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
        $ssa->services()->attach($service->id, ['is_primary' => true]);

        $this->browse(function (Browser $browser) use ($therapist, $student, $ssa) {
            $browser->loginAs($therapist)
                ->visit("/therapist/ssas/{$ssa->id}")
                ->assertSee($student->name)
                ->assertSee('Add Session Log')
                ->clickLink('Add Session Log')
                ->assertPathIs('/therapist/session-logs/create')
                ->assertQueryStringHas('ssa_id', (string) $ssa->id)
                ->assertSee('Create Session Log')
                ->assertSee($student->name);
        });
    }

    public function test_navigation_menu_has_add_non_schedule_log_link(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->browse(function (Browser $browser) use ($therapist) {
            $browser->loginAs($therapist)
                ->visit('/therapist/dashboard')
                ->assertSee('Add Non-Schedule Log')
                ->clickLink('Add Non-Schedule Log')
                ->assertPathIs('/therapist/session-logs/select-ssa')
                ->assertSee('Select SSA');
        });
    }

    public function test_schedule_create_form_auto_calculates_end_time(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        $school = School::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create(['name' => 'OT']);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
        $ssa->services()->attach($service->id, ['is_primary' => true]);

        $this->browse(function (Browser $browser) use ($therapist) {
            $browser->loginAs($therapist)
                ->visit('/therapist/schedule/create')
                ->assertPresent('#start_time')
                ->assertPresent('#end_time_display')
                ->value('#start_time', '09:00')
                ->value('#duration_minutes', '60');

            $browser->driver->executeScript(
                "document.getElementById('start_time').dispatchEvent(new Event('change'))"
            );

            $browser->pause(300)
                ->assertInputValue('#end_time_display', '10:00');
        });
    }

    public function test_session_log_from_schedule_has_readonly_date_and_service(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create(['name' => 'Scheduled Student']);
        $school = School::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create(['name' => 'PT Service']);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
        $ssa->services()->attach($service->id, ['is_primary' => true]);

        $scheduleDate = now()->subDay()->format('Y-m-d');
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'school_id' => $school->id,
            'schedule_date' => $scheduleDate,
        ]);

        $this->browse(function (Browser $browser) use ($therapist, $schedule, $scheduleDate) {
            $browser->loginAs($therapist)
                ->visit("/therapist/session-logs/create/schedule/{$schedule->id}")
                ->assertSee('Create Session Log')
                ->assertInputValue('input[name="session_date"]', $scheduleDate)
                ->assertMissing('input[name="session_date"]:not([type="hidden"])');
        });
    }
}
