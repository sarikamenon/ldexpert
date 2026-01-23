<?php

namespace Tests\Browser;

use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SchoolCalendarEventsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_can_create_calendar_event_and_therapist_sees_it(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create();
        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $holidayDate = now()->addDays(2)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($admin, $therapist, $school, $holidayDate) {
            $browser->loginAs($admin)
                ->visit(route('admin.schools.show', ['school' => $school, 'tab' => 'calendar']))
                ->type('event_title', 'Winter Break')
                ->select('event_type', 'holiday')
                ->type('event_start_date', $holidayDate)
                ->type('event_end_date', $holidayDate)
                ->press('Save Event')
                ->waitForText('Winter Break')
                ->assertSee('Winter Break');

            $browser->loginAs($therapist)
                ->visit(route('therapist.schedule.calendar', ['date' => $holidayDate]))
                ->waitForText('School Events')
                ->assertSee('Winter Break');
        });
    }
}
