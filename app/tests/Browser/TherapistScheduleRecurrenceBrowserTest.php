<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser tests for the schedule create form — all recurrence options.
 *
 * Covers: none, daily, weekly, bi_weekly, monthly, custom_weekly (private student only).
 */
final class TherapistScheduleRecurrenceBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Override to run migrate:fresh only — skip migrate:rollback which hits broken down() methods.
     */
    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
    }

    private User $therapist;

    private User $student;

    private ServiceSupportAgreement $ssa;

    private Service $service;

    /** Non-private school setup (no custom_weekly option) */
    protected function setUp(): void
    {
        parent::setUp();

        $this->therapist = User::factory()->therapist()->create([
            'email' => 'therapist+schedule@example.com',
            'password' => bcrypt('password'),
            'timezone' => 'America/Chicago',
        ]);

        $school = School::factory()->create(['is_private_student' => false]);

        $this->student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $this->student->id,
            'school_id' => $school->id,
        ]);

        $this->therapist->students()->attach($this->student->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $this->service = Service::factory()->create([
            'status' => ServiceStatus::ACTIVE,
            'is_group_service' => false,
        ]);

        $this->ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $this->service->id,
            'assigned_therapist_id' => $this->therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Next Monday in Y-m-d format */
    private function nextMonday(): string
    {
        return Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    }

    /** A weekday N weeks after a given date */
    private function weekdayPlusWeeks(string $date, int $weeks): string
    {
        return Carbon::parse($date)->addWeeks($weeks)->format('Y-m-d');
    }

    private function createPageUrl(): string
    {
        return '/therapist/schedule/create?ssa_id='.$this->ssa->id;
    }

    // -------------------------------------------------------------------------
    // Visibility tests
    // -------------------------------------------------------------------------

    public function test_recurrence_section_is_visible_on_create_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(1000)
                ->assertSee('Recurrence Options')
                ->assertSee('Recurrence Type');
        });
    }

    public function test_custom_weekly_option_is_visible_for_non_private_student(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(800)
                ->assertSee('Custom Weekly');
        });
    }

    public function test_custom_weekly_hides_saturday_sunday_when_school_disallows_weekends(): void
    {
        $privateSsa = $this->makePrivateStudentSetup(false);

        $this->browse(function (Browser $browser) use ($privateSsa) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(600);
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#weekly_days_container')
                ->assertPresent("input[name='weekly_days[]'][value='monday']")
                ->assertPresent("input[name='weekly_days[]'][value='friday']")
                ->assertMissing("input[name='weekly_days[]'][value='saturday']")
                ->assertMissing("input[name='weekly_days[]'][value='sunday']");
        });
    }

    public function test_custom_weekly_shows_saturday_sunday_when_school_allows_weekends(): void
    {
        $privateSsa = $this->makePrivateStudentSetup(true);

        $this->browse(function (Browser $browser) use ($privateSsa) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(600);
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#weekly_days_container')
                ->assertPresent("input[name='weekly_days[]'][value='saturday']")
                ->assertPresent("input[name='weekly_days[]'][value='sunday']");
        });
    }

    public function test_custom_weekly_option_is_visible_for_private_student(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();

        $this->browse(function (Browser $browser) use ($privateSsa) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(1000)
                ->assertSee('Custom Weekly');
        });
    }

    // -------------------------------------------------------------------------
    // None (single occurrence)
    // -------------------------------------------------------------------------

    public function test_none_recurrence_hides_end_date_and_occurrences(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(500);
            // Select2 — set value directly on underlying select
            $browser->script("$('#recurrence_type').val('none').trigger('change');");
            $browser->pause(400)
                ->assertMissing('#recurrence_end_date_container:not(.hidden)')
                ->assertMissing('#occurrence_dates_container:not(.hidden)');
        });
    }

    public function test_can_submit_none_recurrence_schedule(): void
    {
        $scheduleDate = $this->nextMonday();

        $this->browse(function (Browser $browser) use ($scheduleDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(800);
            $browser->script("$('#recurrence_type').val('none').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->type('#location_details', 'Office A');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(500)->assertPathContains('/therapist/schedule');
        });

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'student_id' => $this->student->id,
            'recurrence_type' => 'none',
        ]);
    }

    // -------------------------------------------------------------------------
    // Daily recurrence
    // -------------------------------------------------------------------------

    public function test_daily_recurrence_shows_end_date_and_occurrences(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 1);

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('daily').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#recurrence_end_date_container');
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->assertVisible('#occurrence_dates_container')
                ->assertSeeIn('#occurrence_dates_container', ' scheduled');
        });
    }

    public function test_can_submit_daily_recurrence_schedule(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = Carbon::parse($scheduleDate)->addDays(4)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('daily').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->type('#location_details', 'Office B');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(500)->assertPathContains('/therapist/schedule');
        });

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'recurrence_type' => 'daily',
        ]);
    }

    // -------------------------------------------------------------------------
    // Weekly recurrence
    // -------------------------------------------------------------------------

    public function test_weekly_recurrence_shows_end_date_and_occurrences(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 3);

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#recurrence_end_date_container');
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->assertVisible('#occurrence_dates_container')
                ->assertSeeIn('#occurrence_dates_container', ' scheduled');
        });
    }

    public function test_can_submit_weekly_recurrence_schedule(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 3);

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('weekly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->type('#location_details', 'Room 101');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(500)->assertPathContains('/therapist/schedule');
        });

        $count = \App\Models\Schedule::where('therapist_id', $this->therapist->id)
            ->where('recurrence_type', 'weekly')
            ->count();
        $this->assertGreaterThan(1, $count);
    }

    // -------------------------------------------------------------------------
    // Bi-weekly recurrence
    // -------------------------------------------------------------------------

    public function test_bi_weekly_recurrence_shows_end_date_and_occurrences(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 6);

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('bi_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#recurrence_end_date_container');
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->assertVisible('#occurrence_dates_container')
                ->assertSeeIn('#occurrence_dates_container', ' scheduled');
        });
    }

    public function test_can_submit_bi_weekly_recurrence_schedule(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 6);

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('bi_weekly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->type('#location_details', 'Room 202');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(500)->assertPathContains('/therapist/schedule');
        });

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'recurrence_type' => 'bi_weekly',
        ]);
    }

    // -------------------------------------------------------------------------
    // Monthly recurrence
    // -------------------------------------------------------------------------

    public function test_monthly_recurrence_shows_end_date_and_occurrences(): void
    {
        $scheduleDate = $this->nextMonday();
        // 2 months keeps both occurrences on weekdays (next Monday + same day 2 months later)
        $endDate = Carbon::parse($scheduleDate)->addMonths(2)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('monthly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#recurrence_end_date_container');
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->assertVisible('#occurrence_dates_container')
                ->assertSeeIn('#occurrence_dates_container', ' scheduled');
        });
    }

    public function test_can_submit_monthly_recurrence_schedule(): void
    {
        $scheduleDate = $this->nextMonday();
        // 2 months: Apr 6 (Mon) → May 6 (Wed) → Jun 6 (Sat=skip). Use 1 month to stay safe.
        $endDate = Carbon::parse($scheduleDate)->addMonths(1)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('monthly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600)
                ->type('#location_details', 'Room 303');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(500)->assertPathContains('/therapist/schedule');
        });

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'recurrence_type' => 'monthly',
        ]);
    }

    // -------------------------------------------------------------------------
    // Custom Weekly recurrence (private student only)
    // -------------------------------------------------------------------------

    /** Shared setup: create a private-school student with an SSA and return it. */
    private function makePrivateStudentSetup(bool $allowsWeekend = false): ServiceSupportAgreement
    {
        $privateSchool = School::factory()->create([
            'is_private_student' => true,
            'allow_weekend_scheduling' => $allowsWeekend,
        ]);
        $privateStudent = User::factory()->student()->create();
        // ->student() afterCreating already made a StudentProfile with a random school;
        // point it at the private school so the controller resolves the right flags.
        $privateStudent->studentProfile()->update(['school_id' => $privateSchool->id]);
        $this->therapist->students()->attach($privateStudent->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        return ServiceSupportAgreement::factory()->create([
            'student_id' => $privateStudent->id,
            'primary_service_id' => $this->service->id,
            'assigned_therapist_id' => $this->therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
        ]);
    }

    public function test_custom_weekly_shows_day_checkboxes_when_selected(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#weekly_days_container')
                ->assertVisible('#recurrence_end_date_container');
        });
    }

    public function test_custom_weekly_day_checkboxes_all_five_weekdays_present(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();

        $this->browse(function (Browser $browser) use ($privateSsa) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#weekly_days_container');

            // All five weekday checkboxes must be present
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
                $browser->assertPresent("input[name='weekly_days[]'][value='$day']");
            }
        });
    }

    public function test_custom_weekly_generates_occurrences_after_selecting_days(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 2);

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(400);
            // Check Monday and Wednesday
            $browser->script("
                document.querySelectorAll('.weekly-day-checkbox').forEach(cb => {
                    if (['monday', 'wednesday'].includes(cb.value)) {
                        cb.checked = true;
                        cb.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            ");
            $browser->pause(600)
                ->assertVisible('#occurrence_dates_container')
                ->assertSeeIn('#occurrence_dates_container', ' scheduled');
        });
    }

    public function test_switching_away_from_custom_weekly_hides_day_checkboxes(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            // First select custom_weekly
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#weekly_days_container');
            // Switch to weekly — day picker should hide
            $browser->script("$('#recurrence_type').val('weekly').trigger('change');");
            $browser->pause(400)
                ->assertMissing('#weekly_days_container:not(.hidden)');
        });
    }

    public function test_can_submit_custom_weekly_schedule_with_selected_days(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 2);

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(400);
            // Select Monday and Thursday
            $browser->script("
                document.querySelectorAll('.weekly-day-checkbox').forEach(cb => {
                    if (['monday', 'thursday'].includes(cb.value)) {
                        cb.checked = true;
                        cb.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            ");
            $browser->pause(600)
                ->type('#location_details', 'Private Office');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(500)->assertPathContains('/therapist/schedule');
        });

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'recurrence_type' => 'custom_weekly',
        ]);
    }

    public function test_additional_dates_section_shows_only_for_custom_weekly(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");

            // Weekly recurrence: additional dates section stays hidden.
            $browser->script("$('#recurrence_type').val('weekly').trigger('change');");
            $browser->pause(400)
                ->assertMissing('#additional_dates_container:not(.hidden)');

            // Custom weekly: additional dates section is revealed with an add button.
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#additional_dates_container')
                ->assertPresent('#add_additional_date_btn');
        });
    }

    public function test_can_add_and_remove_an_additional_date_row(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400)
                ->assertVisible('#additional_dates_container');

            // Add a row.
            $browser->click('#add_additional_date_btn')->pause(300);
            $added = $browser->script("return document.querySelectorAll('.additional-date-input').length;")[0];
            $this->assertSame(1, $added);

            // Remove it.
            $browser->click('.additional-date-remove-btn')->pause(300);
            $remaining = $browser->script("return document.querySelectorAll('.additional-date-input').length;")[0];
            $this->assertSame(0, $remaining);
        });
    }

    public function test_can_submit_custom_weekly_with_an_additional_one_off_date(): void
    {
        $privateSsa = $this->makePrivateStudentSetup();
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 2);
        // First Wednesday — outside the Monday weekly pattern.
        $extraDate = Carbon::parse($scheduleDate)->addDays(2)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($privateSsa, $scheduleDate, $endDate, $extraDate) {
            $browser->loginAs($this->therapist)
                ->visit('/therapist/schedule/create?ssa_id='.$privateSsa->id)
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('custom_weekly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(400);
            // Weekly pattern: Monday only.
            $browser->script("
                document.querySelectorAll('.weekly-day-checkbox').forEach(cb => {
                    if (cb.value === 'monday') {
                        cb.checked = true;
                        cb.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            ");
            $browser->pause(400);
            // Add a one-off Wednesday.
            $browser->click('#add_additional_date_btn')->pause(300);
            $browser->script("var i = document.querySelector('.additional-date-input'); i.value = '$extraDate'; i.dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(300)
                ->type('#location_details', 'Private Office');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            $browser->pause(600)->assertPathContains('/therapist/schedule');
        });

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'schedule_date' => $extraDate,
            'recurrence_type' => 'custom_weekly',
        ]);
    }

    // -------------------------------------------------------------------------
    // Recurrence end date validation (shared behaviour)
    // -------------------------------------------------------------------------

    public function test_end_date_field_is_required_for_any_recurrence_type(): void
    {
        $scheduleDate = $this->nextMonday();

        $this->browse(function (Browser $browser) use ($scheduleDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("document.getElementById('start_time').value = '09:00';");
            $browser->script("document.getElementById('duration_minutes').value = '60';");
            $browser->script("$('#recurrence_type').val('weekly').trigger('change');");
            $browser->pause(400)
                // Do NOT fill recurrence_end_date
                ->type('#location_details', 'Office');

            $browser->script("document.getElementById('scheduleCreateForm').submit();");

            // SweetAlert fires — page should NOT redirect
            $browser->pause(800)
                ->assertPathIs(parse_url($this->createPageUrl(), PHP_URL_PATH));
        });
    }

    public function test_occurrence_remove_button_decrements_session_count(): void
    {
        $scheduleDate = $this->nextMonday();
        $endDate = $this->weekdayPlusWeeks($scheduleDate, 3);

        $this->browse(function (Browser $browser) use ($scheduleDate, $endDate) {
            $browser->loginAs($this->therapist)
                ->visit($this->createPageUrl())
                ->pause(400);
            $browser->script("document.getElementById('schedule_date').value = '$scheduleDate';");
            $browser->script("$('#recurrence_type').val('weekly').trigger('change');");
            $browser->pause(400);
            $browser->script("document.getElementById('recurrence_end_date').value = '$endDate'; document.getElementById('recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));");
            $browser->pause(600);

            // Count before removing
            $countBefore = $browser->script("return document.querySelectorAll('.occurrence-date-row').length;")[0];

            // Click first remove button
            $browser->click('.occurrence-remove-btn')
                ->pause(400);

            $countAfter = $browser->script("return document.querySelectorAll('.occurrence-date-row').length;")[0];

            $this->assertLessThan($countBefore, $countAfter);
        });
    }
}
