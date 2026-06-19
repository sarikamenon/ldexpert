<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Schedule ─────────────────────────────────────────────────────────────────

it('TC-T006 therapist can create a single session schedule', function (): void {
    $admin     = User::factory()->admin()->qa()->create();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $futureDate = now()->addDays(7)->format('Y-m-d');
    $ssaId      = $ssa->id;
    $serviceId  = $service->id;

    $this->browse(function (Browser $browser) use ($therapist, $student, $ssa, $ssaId, $serviceId, $futureDate): void {
        // Step 1 — Calendar: click "Add New Schedule" → SSA selection modal opens
        $browser->loginAs($therapist)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#addScheduleButton')
            ->click('#addScheduleButton')
            ->pause(800);

        // Select SSA in modal (Select2 — must use script(), which returns array not $browser)
        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");

        // Continue navigates to the create page
        $browser->press('Continue')
            ->waitForText('Create New Schedule', 10)
            ->pause(800);

        // Step 2 — Create page: set all fields via script then type location and submit
        $browser->script("
            jQuery('#service_id').val('{$serviceId}').trigger('change');
            jQuery('#recurrence_type').val('none').trigger('change');
            document.querySelector('#schedule_date').value = '{$futureDate}';
            document.querySelector('#schedule_date').dispatchEvent(new Event('change', {bubbles: true}));
            document.querySelector('#start_time').value = '09:00';
        ");

        $browser->type('location_details', 'https://meet.google.com/tc006-test-session')
            ->press('Create Schedule')
            ->pause(2000);
    });

    $this->assertDatabaseHas('schedules', [
        'therapist_id' => $therapist->id,
        'student_id'   => $student->id,
        'ssa_id'       => $ssa->id,
    ]);
});

it('TC-T007 therapist can create a recurring weekly schedule', function (): void {
    $admin     = User::factory()->admin()->qa()->create();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $startDate = now()->addDays(7)->format('Y-m-d');
    $endDate   = now()->addDays(28)->format('Y-m-d');
    $ssaId     = $ssa->id;
    $serviceId = $service->id;

    $this->browse(function (Browser $browser) use ($therapist, $student, $ssa, $ssaId, $serviceId, $startDate, $endDate): void {
        // Step 1 — Calendar: open SSA modal and select SSA
        $browser->loginAs($therapist)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#addScheduleButton')
            ->click('#addScheduleButton')
            ->pause(800);

        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");

        $browser->press('Continue')
            ->waitForText('Create New Schedule', 10)
            ->pause(800);

        // Step 2 — Create page: set service and recurrence type first
        $browser->script("
            jQuery('#service_id').val('{$serviceId}').trigger('change');
            jQuery('#recurrence_type').val('weekly').trigger('change');
        ");

        // Set schedule_date — triggers updateEndDateMinDate() and updateOccurrenceDates() (no endDate yet)
        $browser->script("
            document.querySelector('#schedule_date').value = '{$startDate}';
            document.querySelector('#schedule_date').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->pause(300);

        // Set recurrence_end_date — triggers occurrence date generation by the recurrence JS
        $browser->script("
            document.querySelector('#recurrence_end_date').value = '{$endDate}';
            document.querySelector('#recurrence_end_date').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->pause(600);

        $browser->script("document.querySelector('#start_time').value = '10:00';");

        $browser->type('location_details', 'https://meet.google.com/tc007-test-session')
            ->press('Create Schedule')
            ->pause(2000);
    });

    $scheduleCount = \App\Models\Schedule::where('therapist_id', $therapist->id)
        ->where('student_id', $student->id)
        ->count();
    expect($scheduleCount)->toBeGreaterThan(1);
});

it('TC-T008 therapist cannot create schedule with a past date', function (): void {
    $admin     = User::factory()->admin()->qa()->create();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $pastDate  = now()->subDays(7)->format('Y-m-d');
    $ssaId     = $ssa->id;
    $serviceId = $service->id;

    $this->browse(function (Browser $browser) use ($therapist, $ssaId, $serviceId, $pastDate): void {
        // Step 1 — Calendar: open SSA modal
        $browser->loginAs($therapist)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#addScheduleButton')
            ->click('#addScheduleButton')
            ->pause(800);

        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");

        $browser->press('Continue')
            ->waitForText('Create New Schedule', 10)
            ->pause(800);

        // Step 2 — Create page: submit with a past date
        $browser->script("
            jQuery('#service_id').val('{$serviceId}').trigger('change');
            jQuery('#recurrence_type').val('none').trigger('change');
            document.querySelector('#schedule_date').value = '{$pastDate}';
            document.querySelector('#schedule_date').dispatchEvent(new Event('change', {bubbles: true}));
            document.querySelector('#start_time').value = '09:00';
        ");

        $browser->type('location_details', 'https://meet.google.com/tc008-test-session')
            ->press('Create Schedule')
            ->pause(1000);

        // Server validation rejects past dates — error message rendered on the form
        $browser->assertSee('past');
    });

    $this->assertDatabaseMissing('schedules', [
        'therapist_id' => $therapist->id,
        'student_id'   => $student->id,
    ]);
});

it('TC-T009 therapist cannot create schedule without selecting a student', function (): void {
    $admin     = User::factory()->admin()->qa()->create();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    // SSA is required so the "Add New Schedule" button is enabled (not disabled)
    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#addScheduleButton')
            ->click('#addScheduleButton')
            ->pause(800);

        // Attempt to continue without selecting an SSA.
        // The JS handler guards: if (!ssaId) return — no navigation occurs.
        $browser->press('Continue')
            ->pause(500);

        // Still on the calendar page — no redirect to create page
        $browser->assertPathIs('/therapist/schedule/calendar');
    });
});

it('TC-T010 schedule calendar loads empty state when therapist has no schedules', function (): void {
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create();

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/schedule/calendar')
            ->assertDontSee('Whoops')
            ->assertDontSee('500');
    });
});
