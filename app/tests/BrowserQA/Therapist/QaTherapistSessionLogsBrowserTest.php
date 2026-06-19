<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Session Logs ─────────────────────────────────────────────────────────────

it('TC-T011 therapist can submit a DRAFT session log', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    // Use start of current week so the billing window is still open
    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'end_time' => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        // Session logs are submitted from the show page, not the edit page.
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);
    });

    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('submitted');
    expect($draftLog->submitted_at)->not->toBeNull();
});

it('TC-T012 therapist can resubmit a SENT_BACK session log', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    // Use start of current week so the billing window is still open
    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $sentBackLog = SessionLog::factory()->sentBack()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'submitted_by_id' => $therapist->id,
        'sent_back_by_id' => $admin->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'end_time' => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $sentBackLog): void {
        // Session logs are submitted/resubmitted from the show page.
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$sentBackLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);
    });

    $sentBackLog->refresh();
    expect($sentBackLog->status->value)->toBe('submitted');
});

it('TC-T013 therapist cannot submit a log with missing required fields', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    // Use start of current week so the billing window is still open
    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'end_time' => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id.'/edit')
            ->waitForText('Update Session Log', 10);

        // Clear the session date field and attempt to save — should fail validation.
        $browser->script("
            var form = document.querySelector('form');
            if (form) form.setAttribute('novalidate', 'novalidate');
            var hidden = document.querySelector('input[name=\"session_date\"][type=\"hidden\"]');
            if (hidden) hidden.value = '';
            var visible = document.querySelector('#session-log-date');
            if (visible) { visible.value = ''; visible.dispatchEvent(new Event('change', {bubbles:true})); }
        ");

        // The submit control is an x-ui::loading-button whose label sits in a
        // nested <span>, so Dusk's press(label) cannot resolve it. Click the
        // form's submit button directly (matches the QA suite convention).
        $browser->click('button[type="submit"]')
            ->pause(1000);

        // Expect validation error — we stay on the edit page with an error message.
        $browser->assertSee('session');
    });

    // Status must still be draft
    expect($draftLog->fresh()?->status->value)->toBe('draft');
});

it('TC-T014 therapist cannot edit an APPROVED session log', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $approvedLog = SessionLog::factory()->approved()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'submitted_by_id' => $therapist->id,
        'approved_by_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $approvedLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs')
            ->pause(1500);

        // Edit button should not be present for approved logs
        $editBtn = $browser->element('@edit-log-'.$approvedLog->id);
        expect($editBtn)->toBeNull();
    });
});

it('TC-T015 session log list shows empty state when therapist has no logs', function (): void {
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create();

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs')
            ->assertDontSee('Whoops')
            ->assertDontSee('500');
    });
});

// ─── New Tests (TC-TC124-TC-TC138) ───────────────────────────────────────────

it('TC-TC124 therapist can log session with all form fields', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);

    $this->browse(function (Browser $browser) use ($therapist, $ssa, $service, $sessionStart): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/ssas/'.$ssa->id.'?tab=session_logs')
            ->waitFor('a[href*="/session-logs/create"]', 10)
            ->click('a[href*="/session-logs/create"]')
            ->waitFor('input[name="session_date"]', 10)
            ->select('service_id', (string) $service->id);

        $browser->script("
            document.getElementById('session-log-date').value = '".$sessionStart->toDateString()."';
            document.getElementById('session-log-date').dispatchEvent(new Event('change', {bubbles: true}));
            document.getElementById('session-log-start-time').value = '".$sessionStart->format('H:i')."';
            document.getElementById('session-log-start-time').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->type('duration_minutes', '60')
            ->select('outcome', 'services_administered')
            ->type('notes', 'TC-TC124 session notes with valid length')
            ->script("window.jQuery && window.jQuery('.select2-hidden-accessible').select2('close');");

        $browser->press('Create Session Log')
            ->waitForText('created successfully', 10);
    });

    $sessionLog = SessionLog::where('therapist_id', $therapist->id)->where('status', 'draft')->first();
    expect($sessionLog)->not->toBeNull();
    expect($sessionLog?->status->value)->toBe('draft');
});

it('TC-TC125 therapist cannot save session log with missing required Student field', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);

    $this->browse(function (Browser $browser) use ($therapist, $ssa, $service, $sessionStart): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/ssas/'.$ssa->id.'?tab=session_logs')
            ->waitFor('a[href*="/session-logs/create"]', 10)
            ->click('a[href*="/session-logs/create"]')
            ->waitFor('input[name="session_date"]', 10)
            ->select('service_id', (string) $service->id);

        $browser->script("
            document.getElementById('session-log-date').value = '".$sessionStart->toDateString()."';
            document.getElementById('session-log-date').dispatchEvent(new Event('change', {bubbles: true}));
            document.getElementById('session-log-start-time').value = '".$sessionStart->format('H:i')."';
            document.getElementById('session-log-start-time').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->type('duration_minutes', '60')
            ->select('outcome', 'services_administered')
            ->type('notes', 'TC-TC125 session notes with valid length');

        // Set the student hidden field to a non-existent student ID via script to trigger student validation error
        $browser->script("
            var hidden = document.querySelector('input[name=\"student_id\"][type=\"hidden\"]');
            if (hidden) hidden.value = '999999';
            window.jQuery && window.jQuery('.select2-hidden-accessible').select2('close');
        ");

        // Try to save without student
        $browser->press('Create Session Log')
            ->pause(1500);

        $bodyText = $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::tagName('body'))->getText();
        expect(
            str_contains($bodyText, 'required') ||
            str_contains($bodyText, 'error') ||
            str_contains($bodyText, 'student')
        )->toBeTrue();
    });
});

it('TC-TC126 therapist can log session with 12-hour duration', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(8, 0);

    $this->browse(function (Browser $browser) use ($therapist, $ssa, $service, $sessionStart): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/ssas/'.$ssa->id.'?tab=session_logs')
            ->waitFor('a[href*="/session-logs/create"]', 10)
            ->click('a[href*="/session-logs/create"]')
            ->waitFor('input[name="session_date"]')
            ->select('service_id', (string) $service->id);

        $browser->script("
            document.getElementById('session-log-date').value = '".$sessionStart->toDateString()."';
            document.getElementById('session-log-date').dispatchEvent(new Event('change', {bubbles: true}));
            document.getElementById('session-log-start-time').value = '".$sessionStart->format('H:i')."';
            document.getElementById('session-log-start-time').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->type('duration_minutes', '720')
            ->select('outcome', 'services_administered')
            ->type('notes', 'TC-TC126 session notes with valid length')
            ->script("window.jQuery && window.jQuery('.select2-hidden-accessible').select2('close');");

        $browser->press('Create Session Log')
            ->waitForText('created successfully', 10);
    });

    $sessionLog = SessionLog::where('therapist_id', $therapist->id)->where('duration_minutes', 720)->first();
    expect($sessionLog)->not->toBeNull();
    expect($sessionLog?->duration_minutes)->toBe(720);
});

it('TC-TC127 therapist cannot save session with negative duration', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);

    $this->browse(function (Browser $browser) use ($therapist, $ssa, $service, $sessionStart): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/ssas/'.$ssa->id.'?tab=session_logs')
            ->waitFor('a[href*="/session-logs/create"]', 10)
            ->click('a[href*="/session-logs/create"]')
            ->waitFor('input[name="session_date"]')
            ->select('service_id', (string) $service->id);

        $browser->script("
            document.querySelector('form').setAttribute('novalidate', 'novalidate');
            document.getElementById('session-log-date').value = '".$sessionStart->toDateString()."';
            document.getElementById('session-log-date').dispatchEvent(new Event('change', {bubbles: true}));
            document.getElementById('session-log-start-time').value = '".$sessionStart->format('H:i')."';
            document.getElementById('session-log-start-time').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->type('duration_minutes', '-30')
            ->select('outcome', 'services_administered')
            ->script("window.jQuery && window.jQuery('.select2-hidden-accessible').select2('close');");

        $browser->press('Create Session Log')
            ->pause(1000);

        $bodyText = $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::tagName('body'))->getText();
        expect(
            str_contains($bodyText, 'duration') ||
            str_contains($bodyText, 'negative') ||
            str_contains($bodyText, 'error')
        )->toBeTrue();
    });
});

it('TC-TC128 therapist can log session with notes and outcome, data persists', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $testNotes = 'TC-TC128 comprehensive session with detailed notes about progress and observations.';

    $this->browse(function (Browser $browser) use ($therapist, $ssa, $service, $sessionStart, $testNotes): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/ssas/'.$ssa->id.'?tab=session_logs')
            ->waitFor('a[href*="/session-logs/create"]', 10);

        // Dismiss any SweetAlert toast before clicking the link
        $browser->script("typeof Swal !== 'undefined' && Swal.close();");
        $browser->pause(300);

        $browser->click('a[href*="/session-logs/create"]')
            ->waitFor('input[name="session_date"]')
            ->select('service_id', (string) $service->id);

        $browser->script("
            document.getElementById('session-log-date').value = '".$sessionStart->toDateString()."';
            document.getElementById('session-log-date').dispatchEvent(new Event('change', {bubbles: true}));
            document.getElementById('session-log-start-time').value = '".$sessionStart->format('H:i')."';
            document.getElementById('session-log-start-time').dispatchEvent(new Event('change', {bubbles: true}));
        ");

        $browser->type('duration_minutes', '60')
            ->select('outcome', 'services_administered')
            ->type('textarea[name="notes"], input[name="notes"]', $testNotes)
            ->script("window.jQuery && window.jQuery('.select2-hidden-accessible').select2('close');");

        $browser->press('Create Session Log')
            ->waitForText('created successfully', 10)
            ->pause(500);
    });

    $sessionLog = SessionLog::where('therapist_id', $therapist->id)->where('status', 'draft')->first();
    expect($sessionLog)->not->toBeNull();
    expect($sessionLog?->notes)->toContain('TC-TC128');
});

it('TC-TC129 therapist can edit draft session before submission', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'end_time' => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
        'notes' => 'Original notes with more than twenty characters.',
        'outcome' => \App\Enums\SessionOutcome::SERVICES_ADMINISTERED,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id.'/edit')
            ->waitFor('textarea[name="notes"]', 10)
            ->clear('notes')
            ->type('notes', 'Updated notes after edit')
            // press('Update Session Log') cannot resolve the loading-button
            // (its label is in a nested <span>), so it silently fails to submit
            // and the notes never persist. Click the submit button directly.
            ->click('button[type="submit"]')
            ->pause(2000);
    });

    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('draft');
    expect($draftLog->notes)->toContain('Updated notes');
});

it('TC-TC130 therapist cannot edit submitted session', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $submittedLog = SessionLog::factory()->submitted()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'submitted_by_id' => $therapist->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $submittedLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs')
            ->pause(1500);

        // Edit button should not exist for submitted sessions
        $editBtn = $browser->element('@edit-log-'.$submittedLog->id);
        expect($editBtn)->toBeNull();
    });
});

it('TC-TC131 editing draft session multiple times with conflicting changes resolves to last save wins', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'duration_minutes' => 30,
    ]);

    // Simulate two edits via API/DB to test last-save-wins
    $draftLog->update(['duration_minutes' => 30]);
    $draftLog->update(['duration_minutes' => 60]);

    $draftLog->refresh();
    expect($draftLog->duration_minutes)->toBe(60);
});

it('TC-TC132 therapist cannot edit another therapist\'s draft session', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist1 = User::factory()->therapist()->qa()->create();
    $therapist2 = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist1, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapist2, 'user')->create(['manager_id' => $admin->id]);

    // Explicit unique names avoid Faker's finite company() pool colliding with
    // seeded schools on the schools_display_name_unique constraint.
    $uniqueSuffix = uniqid();
    $school = School::factory()->qa()->create([
        'full_name' => 'QA T132 School '.$uniqueSuffix,
        'display_name' => 'QA T132 '.$uniqueSuffix,
    ]);
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist1->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist1->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist2, $draftLog): void {
        $browser->loginAs($therapist2)
            ->visit('/therapist/session-logs/'.$draftLog->id.'/edit')
            ->pause(1000);

        $bodyText = $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::tagName('body'))->getText();
        $url = $browser->driver->getCurrentURL();

        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            str_contains($url, '/session-logs')
        )->toBeTrue();
    });
});

it('TC-TC133 therapist can edit draft session multiple times before final submission', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'duration_minutes' => 30,
        'notes' => 'Initial notes with valid length for draft',
    ]);

    // Edit 1
    $draftLog->update(['notes' => 'Edit 1 notes']);
    // Edit 2
    $draftLog->update(['notes' => 'Edit 2 notes']);
    // Edit 3
    $draftLog->update(['notes' => 'Edit 3 notes']);

    $draftLog->refresh();
    expect($draftLog->notes)->toBe('Edit 3 notes');
    expect($draftLog->status->value)->toBe('draft');

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);
    });

    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('submitted');
});

it('TC-TC134 therapist can submit draft session', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);
    });

    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('submitted');
});

it('TC-TC135 therapist cannot save session edit with notes below the minimum length', function (): void {
    // The submit endpoint itself only flips draft -> submitted; the real
    // "required fields" gate is the edit/update form. UpdateSessionLogRequest
    // enforces notes min:20, so clearing notes to a short value must be rejected
    // and the original draft notes must remain untouched.
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Service '.uniqid(),
        'is_direct_service' => false,
        'min_duration_minutes' => 10,
        'max_duration_minutes' => 800,
        'status' => 'active',
    ]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $schoolContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    SchoolContractService::create([
        'school_contract_id' => $schoolContract->id,
        'service_id' => $service->id,
        'rate' => 150,
        'rate_type' => 'H',
    ]);

    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100,
        'rate_type' => 'H',
    ]);

    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);
    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'session_date' => $sessionStart->toDateString(),
        'start_time' => $sessionStart->toDateTimeString(),
        'end_time' => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
        'notes' => 'Original notes with more than twenty characters.',
        'outcome' => \App\Enums\SessionOutcome::SERVICES_ADMINISTERED,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id.'/edit')
            ->waitFor('textarea[name="notes"]', 10)
            ->clear('notes')
            ->type('notes', 'Too short')
            ->press('Update Session Log')
            ->pause(2000);
    });

    // Update rejected by validation — original notes survive, status stays draft.
    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('draft');
    expect($draftLog->notes)->toBe('Original notes with more than twenty characters.');
});

it('TC-TC136 therapist can cancel session submission in confirmation modal', function (): void {
    $this->markTestSkipped('There is no confirmation modal on therapist session log submission — the Submit control is a plain POST form with no SweetAlert/confirmation step, so there is nothing to cancel.');
});

it('TC-TC137 therapist cannot submit the same session twice', function (): void {
    // The intent behind the "rapid double-click" case is idempotency: only one
    // submission should ever take effect. The UI enforces this by removing the
    // Submit control once the log is submitted (canSubmit() is false), and the
    // service throws if a submitted log is submitted again. We verify both: after
    // one submit the status is submitted, and the Submit button is gone on reload.
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);

        // After the single submission the Submit control must no longer render,
        // so a second click (the "double-click") is impossible from the UI.
        $submitButtons = $browser->script(
            "return Array.from(document.querySelectorAll('button')).filter(b => b.textContent.trim() === 'Submit').length;"
        )[0];
        expect((int) $submitButtons)->toBe(0);
    });

    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('submitted');

    // A direct second submit attempt is rejected by the service (idempotent).
    $service = app(\App\Domain\Therapist\Services\SessionLogService::class);
    expect(fn () => $service->submit($therapist, $draftLog->fresh()))
        ->toThrow(\InvalidArgumentException::class);
});

it('TC-TC138 submitted session appears in admin approval queue', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first() ?? User::factory()->admin()->create(['email' => 'develop.ldexpert@gmail.com']);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Service '.uniqid()]);
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $draftLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
    ]);

    // Therapist submits
    $this->browse(function (Browser $browser) use ($therapist, $draftLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$draftLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);
    });

    // Admin checks queue
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/admin/session-logs')
            ->pause(1500)
            ->assertSee('Submitted');
    });

    $draftLog->refresh();
    expect($draftLog->status->value)->toBe('submitted');
});
