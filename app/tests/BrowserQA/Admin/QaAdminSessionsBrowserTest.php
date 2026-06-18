<?php

declare(strict_types=1);

use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

/**
 * Admin Session & Contract Management QA Tests
 *
 * LOCATOR GUIDE:
 * - Session Logs List: #sessionLogsTable
 * - Approve session: form[action*="approve"] button[type="submit"]
 * - Send back: a[href="#send-back-form"] → textarea[name="comment"] → form[action*="send-back"] button[type="submit"]
 * - Cancel session: form[action*="cancel"] button[type="submit"]
 * - SSA Deactivate: button.change-status-btn[data-status="deactivated"] + .swal2-confirm
 * - School contract school selector: select[name="school_id"] (plain <select>, not x-ui::select)
 * - School contract dates: input[name="start_date"], input[name="end_date"]
 * - School contract Add Service: #addServiceRow (NEVER click in tests — see below)
 * - Therapist contract therapist selector: select[name="therapist_id"] (plain <select>)
 * - Therapist contract Add Service: #addTherapistServiceRow (NEVER click in tests — see below)
 * - SSA form: x-ui::select IDs — #student_id, #primary_service_id, #assigned_therapist_id, #frequency
 * - SSA form inputs: #start_date, #end_date, #minutes_per_session, #tho_minutes
 * - Session log edit rate override: select#therapist_rate_type, input#therapist_rate_amount
 * - TherapistContract therapist_id is FK to therapist_profiles.id — always pass $profile->id not $user->id
 * - x-ui::select is Select2-enhanced; ->select() works via the native underlying <select>
 *
 * CONTRACT CREATE FORM SERVICE ROWS:
 * Both school and therapist contract create forms pre-populate ONE empty service row at
 * index 0 (services[0][*]) when the page loads. Do NOT click #addServiceRow or
 * #addTherapistServiceRow — that adds a SECOND row (index 1) which is never filled,
 * causing the services.min:1 validation to fail and redirecting back to /create.
 * Always fill services[0][service_id], services[0][rate], services[0][rate_type] directly.
 */

// ─── Session Logs: List & View ────────────────────────────────

it('TC-A100 Admin can view session logs list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs')
            ->waitFor('#sessionLogsTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-A101 Admin can view individual session log details', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
    ]);

    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->toDateString(),
    ]);

    $this->browse(function (Browser $browser) use ($admin, $sessionLog): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/'.$sessionLog->id)
            ->waitFor('h1, h2', 20)
            ->assertSee('Session Log');
    });
});

// ─── Session Logs: Approval ───────────────────────────────────

it('TC-A102 Admin can approve a submitted session log', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
    ]);

    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->toDateString(),
        'status' => 'submitted',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $sessionLog): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/'.$sessionLog->id)
            ->waitFor('form[action*="approve"] button[type="submit"]', 20)
            ->waitForReload(function (Browser $b): void {
                $b->click('form[action*="approve"] button[type="submit"]');
            }, 15);
    });

    $this->assertDatabaseHas('session_logs', [
        'id' => $sessionLog->id,
        'status' => 'approved',
    ]);
});

it('TC-A103 Admin can send back a session log for revision', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
    ]);

    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->toDateString(),
        'status' => 'submitted',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $sessionLog): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/'.$sessionLog->id)
            ->waitFor('a[href="#send-back-form"]', 20)
            ->click('a[href="#send-back-form"]')
            ->pause(300)
            ->type('textarea[name="comment"]', 'Please correct the service duration')
            ->waitForReload(function (Browser $b): void {
                $b->click('form[action*="send-back"] button[type="submit"]');
            }, 15);
    });

    $this->assertDatabaseHas('session_logs', [
        'id' => $sessionLog->id,
        'status' => 'sent_back',
    ]);
});

it('TC-A104 Admin can cancel a session log', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
    ]);

    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->toDateString(),
        'status' => 'submitted',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $sessionLog): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/'.$sessionLog->id)
            ->waitFor('form[action*="cancel"] button[type="submit"]', 20)
            ->waitForReload(function (Browser $b): void {
                $b->click('form[action*="cancel"] button[type="submit"]');
            }, 15);
    });

    $this->assertDatabaseHas('session_logs', [
        'id' => $sessionLog->id,
        'status' => 'cancelled',
    ]);
});

// ─── Service Support Agreements: Create ────────────────────────

it('TC-A110 Admin can create an SSA with student and therapist', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    // Use a seeded active service; fall back to factory if none available
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();

    $this->browse(function (Browser $browser) use ($admin, $student, $service): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ssas/create')
            // Full-suite Chrome is under more memory pressure; increase timeout to 60s
            ->waitFor('#student_id', 60)
            // Fill all fields AND submit inside waitForReload so the reload sentinel
            // is guaranteed to be in place before form.submit() navigates away.
            // Using form.submit() (not button click) bypasses any headless-Chrome
            // click-delivery issues that cause waitForReload to time out.
            ->waitForReload(function (Browser $b) use ($student, $service): void {
                // Fill all fields via JS (no change events — keeps headless Chrome stable)
                $b->script("(function() {
                    var sid  = document.getElementById('student_id');
                    var psid = document.getElementById('primary_service_id');
                    var sd   = document.getElementById('start_date');
                    var ed   = document.getElementById('end_date');
                    var mps  = document.getElementById('minutes_per_session');
                    var freq = document.getElementById('frequency');
                    var spf  = document.getElementById('sessions_per_frequency');
                    var tho  = document.getElementById('tho_minutes');
                    if (sid)  sid.value  = '".$student->id."';
                    if (psid) psid.value = '".$service->id."';
                    if (sd)   sd.value   = '".now()->toDateString()."';
                    if (ed)   ed.value   = '".now()->addYear()->toDateString()."';
                    if (freq) freq.value = 'weekly';
                    if (mps)  mps.value  = '30';
                    if (spf)  spf.value  = '1';
                    if (tho)  tho.value  = '30';
                })()");
                // Click via Dusk's WebDriver element click (carries the Dusk session cookie)
                $b->click('button[type="submit"]');
            }, 30)
            ->assertPathContains('/admin/ssas');
    });

    $this->assertDatabaseHas('service_support_agreements', [
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
    ]);
});

it('TC-A111 Admin can view SSA list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ssas')
            ->waitFor('#ssasTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-A112 Admin can edit an SSA', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    // ServiceSupportAgreement factory injects school_id which doesn't exist in the table;
    // use direct create() with only the actual table columns.
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $ssa): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ssas/'.$ssa->id.'/edit')
            ->waitFor('#start_date', 20)
            // Submit the form as-is; waitForReload handles redirect after save
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/ssas');
    });
});

it('TC-A113 Admin can deactivate an SSA', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    // ServiceSupportAgreement factory injects school_id which doesn't exist in the table;
    // use direct create() with status=active so the deactivate button renders.
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
        'status' => 'active',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $ssa): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ssas/'.$ssa->id)
            ->waitFor('button.change-status-btn[data-status="deactivated"]', 20)
            ->click('button.change-status-btn[data-status="deactivated"]')
            ->waitFor('.swal2-confirm', 10)
            ->click('.swal2-confirm')
            // Wait for AJAX status change then navigate away so that the session's
            // url.intended is not left pointing at the SSA page (which is deleted in
            // cleanUpQaTestData). If that URL persists into the next test's loginAs,
            // redirect()->intended() would send the new browser to a 404.
            ->pause(2000)
            ->visit('/admin/dashboard');
    });

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'status' => 'deactivated',
    ]);
});

// ─── Contracts: School ────────────────────────────────────────

it('TC-A120 Admin can create a school contract', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    // Always use a fresh QA school so cleanUpQaTestData() can find and delete
    // the resulting school_contract (cleanup matches school_id IN qaSchoolIds).
    // JS sets the hidden select value directly so the school doesn't need to be
    // pre-loaded in the dropdown.
    $school = $this->createQaSchool();
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();

    $this->browse(function (Browser $browser) use ($admin, $school, $service): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/contracts/schools/create')
            // Full-suite Chrome is under more memory pressure; increase timeout to 60s
            ->waitFor('input[name="start_date"]', 60)
            // Fill all fields AND submit inside waitForReload so the reload sentinel
            // is in place before the click navigates away. end_date is set explicitly
            // (required|after:start_date) rather than relying on the page's auto-fill
            // JS, which is racy under full-suite browser load and left end_date empty.
            ->waitForReload(function (Browser $b) use ($school, $service): void {
                $b->script("(function() {
                    var school = document.querySelector('select[name=\"school_id\"]');
                    var sd = document.querySelector('input[name=\"start_date\"]');
                    var ed = document.querySelector('input[name=\"end_date\"]');
                    var sid = document.querySelector('select[name=\"services[0][service_id]\"]');
                    var sr = document.querySelector('input[name=\"services[0][rate]\"]');
                    var srt = document.querySelector('select[name=\"services[0][rate_type]\"]');
                    var nsr = document.querySelector('input[name=\"services[0][no_show_rate]\"]');
                    if (school) school.value = '".$school->id."';
                    if (sd) sd.value = '".now()->subYear()->toDateString()."';
                    if (ed) ed.value = '".now()->addYear()->toDateString()."';
                    if (sid) sid.value = '".$service->id."';
                    if (sr) sr.value = '50.00';
                    if (srt) srt.value = 'H';
                    // no_show_rate_type defaults to 'H' on the form, which makes
                    // no_show_rate required (required_with) — set it so validation passes.
                    if (nsr) nsr.value = '0';
                })()");
                $b->click('button[type="submit"]');
            }, 30)
            ->assertPathContains('/admin/contracts/schools');

        $this->assertDatabaseHas('school_contracts', [
            'school_id' => $school->id,
        ]);
    });
});

it('TC-A121 Admin can view school contracts list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/contracts/schools')
            ->waitFor('#contractsTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-A122 Admin can edit a school contract', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create(['status' => 'active']);

    // Direct model creation — SchoolContract has $guarded = [] so ::create() works
    $contract = \App\Models\SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'status' => 'active',
    ]);
    // Edit form requires at least one service row (validation: services.min:1)
    \App\Models\SchoolContractService::create([
        'school_contract_id' => $contract->id,
        'service_id' => $service->id,
        'rate' => 100.00,
        'rate_type' => 'H',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $contract): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/contracts/schools/'.$contract->id.'/edit')
            ->waitFor('input[name="start_date"]', 20)
            // Set start_date AND submit inside waitForReload so the sentinel is in
            // place before form.submit() navigates. end_date is already pre-filled
            // from the DB so no change dispatch is needed.
            ->waitForReload(function (Browser $b): void {
                $b->script("(function() {
                    var sd = document.querySelector('input[name=\"start_date\"]');
                    if (sd) sd.value = '".now()->subYear()->toDateString()."';
                })()");
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/contracts/schools');
    });
});

// ─── Contracts: Therapist ─────────────────────────────────────

it('TC-A130 Admin can create a therapist contract', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    // Always use a fresh QA therapist so cleanUpQaTestData() can find and delete
    // the resulting therapist_contract (cleanup matches therapist_id IN qaTherapistProfileIds).
    // JS sets the hidden select value directly so the profile doesn't need to be
    // pre-loaded in the dropdown.
    $therapist = $this->createQaUser('therapist');
    $profile = TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();

    $this->browse(function (Browser $browser) use ($admin, $profile, $service): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/contracts/therapists/create')
            // Full-suite Chrome is under more memory pressure; increase timeout to 60s
            ->waitFor('select[name="therapist_id"]', 60)
            // Fill all fields AND submit inside waitForReload so the reload sentinel
            // is in place before form.submit() navigates away. end_date is set explicitly
            // (required|after:start_date) rather than relying on the page's auto-fill
            // JS, which is racy under full-suite browser load and left end_date empty.
            ->waitForReload(function (Browser $b) use ($profile, $service): void {
                $b->script("(function() {
                    var tid = document.querySelector('select[name=\"therapist_id\"]');
                    var sd = document.querySelector('input[name=\"start_date\"]');
                    var ed = document.querySelector('input[name=\"end_date\"]');
                    var sid = document.querySelector('select[name=\"services[0][service_id]\"]');
                    var sr = document.querySelector('input[name=\"services[0][rate]\"]');
                    var srt = document.querySelector('select[name=\"services[0][rate_type]\"]');
                    var nsr = document.querySelector('input[name=\"services[0][no_show_rate]\"]');
                    if (tid) tid.value = '".$profile->id."';
                    if (sd) sd.value = '".now()->subYear()->toDateString()."';
                    if (ed) ed.value = '".now()->addYear()->toDateString()."';
                    if (sid) sid.value = '".$service->id."';
                    if (sr) sr.value = '50.00';
                    if (srt) srt.value = 'H';
                    // no_show_rate_type defaults to 'H' on the form, which makes
                    // no_show_rate required (required_with) — set it so validation passes.
                    if (nsr) nsr.value = '0';
                })()");
                $b->click('button[type="submit"]');
            }, 30)
            ->assertPathContains('/admin/contracts/therapists');

        $this->assertDatabaseHas('therapist_contracts', [
            'therapist_id' => $profile->id,
        ]);
    });
});

it('TC-A131 Admin can view therapist contracts list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/contracts/therapists')
            ->waitFor('#contractsTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-A132 Admin can edit a therapist contract', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist');
    $profile = TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);
    $service = Service::where('status', 'active')->first() ?? Service::factory()->create(['status' => 'active']);

    // Direct model creation — TherapistContract has $guarded = [] so ::create() works
    $contract = \App\Models\TherapistContract::create([
        'therapist_id' => $profile->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'status' => 'active',
    ]);
    // Edit form requires at least one service row (validation: services.min:1)
    \App\Models\TherapistContractService::create([
        'therapist_contract_id' => $contract->id,
        'service_id' => $service->id,
        'rate' => 100.00,
        'rate_type' => 'H',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $contract): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/contracts/therapists/'.$contract->id.'/edit')
            ->waitFor('input[name="start_date"]', 20)
            // Set start_date AND submit inside waitForReload so the sentinel is in
            // place before form.submit() navigates. end_date is already pre-filled
            // from the DB so no change dispatch is needed.
            ->waitForReload(function (Browser $b): void {
                $b->script("(function() {
                    var sd = document.querySelector('input[name=\"start_date\"]');
                    if (sd) sd.value = '".now()->subYear()->toDateString()."';
                })()");
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/contracts/therapists');
    });
});

// ─── Session Log Import ────────────────────────────────────────

it('TC-A140 Admin can access session log import page', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/import')
            ->waitFor('input[type="file"], h1, h2', 20)
            ->assertPresent('input[type="file"]');
    });
});

it('TC-A141 Admin can upload a session log CSV file', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $csvContent = "student_id,therapist_id,session_date,duration_minutes,service_id\n1,1,2026-06-10,60,1\n";
    $filePath = storage_path('app/test_sessions.csv');
    file_put_contents($filePath, $csvContent);

    $this->browse(function (Browser $browser) use ($admin, $filePath): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/import')
            ->waitFor('input[type="file"]', 20)
            ->attach('input[type="file"]', $filePath)
            ->click('button[type="submit"]')
            ->waitFor('h1, h2', 20);
    });

    @unlink($filePath);
});

it('TC-A142 Admin can view import history', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/imports')
            ->waitFor('#importsTable, table', 20)
            ->assertPresent('table');
    });
});

// ─── Session Log Editing ──────────────────────────────────────

it('TC-A150 Admin can override session log rates', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $service = Service::where('status', 'active')->first() ?? Service::factory()->create();
    $ssa = ServiceSupportAgreement::create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'minutes_per_session' => 30,
        'frequency' => 'weekly',
        'sessions_per_frequency' => 1,
        'tho_minutes' => 120,
    ]);

    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->toDateString(),
        'status' => 'approved',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $sessionLog): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/session-logs/'.$sessionLog->id.'/edit')
            ->waitFor('#therapist_rate_type', 20)
            // Override form fields: therapist_rate_type (H/F), therapist_rate_amount
            ->select('#therapist_rate_type', 'H')
            ->type('#therapist_rate_amount', '75.00')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/session-logs');
    });
});

// ─── Import (placeholder — not yet implemented in UI) ──────────

it('TC-A026 Import students via valid CSV', function (): void {
    // Placeholder: CSV import UI not yet fully implemented for browser testing
})->skip('Placeholder: CSV import flow not yet implemented for browser automation');

it('TC-A027 Import CSV with some skippable rows', function (): void {
    // Placeholder: CSV import UI not yet fully implemented for browser testing
})->skip('Placeholder: CSV import flow not yet implemented for browser automation');

it('TC-A028 Import CSV with wrong column headers', function (): void {
    // Placeholder: CSV import UI not yet fully implemented for browser testing
})->skip('Placeholder: CSV import flow not yet implemented for browser automation');

it('TC-A029 Import empty CSV file', function (): void {
    // Placeholder: CSV import UI not yet fully implemented for browser testing
})->skip('Placeholder: CSV import flow not yet implemented for browser automation');

it('TC-A030 Import CSV with no school selected', function (): void {
    // Placeholder: CSV import UI not yet fully implemented for browser testing
})->skip('Placeholder: CSV import flow not yet implemented for browser automation');

// ─── Session Approval edge cases (placeholder — not yet implemented) ───

it('TC-A038 Approve session and verify hours auto-increment', function (): void {
    // Placeholder: served-hours auto-increment verification not yet implemented
})->skip('Placeholder: served-hours auto-increment not yet covered by browser automation');

it('TC-A039 Approve already approved session', function (): void {
    // Placeholder: idempotent approval behavior not yet covered
})->skip('Placeholder: idempotent approval edge case not yet covered');

it('TC-A040 Approve session with very large duration', function (): void {
    // Placeholder: large-duration edge case not yet covered
})->skip('Placeholder: large-duration approval edge case not yet covered');

it('TC-A041 Approve session that exceeds SSA allocation', function (): void {
    // Placeholder: over-allocation blocking not yet covered by browser automation
})->skip('Placeholder: over-allocation approval blocking not yet covered');

it('TC-A042 Approve multiple sessions and verify hours accumulate', function (): void {
    // Placeholder: multi-session hours accumulation not yet covered
})->skip('Placeholder: multi-session hours accumulation not yet covered');

// ─── Session Log approval edge cases (placeholder — not yet implemented) ──

it('TC-A016 Approve a SUBMITTED session log', function (): void {
    // Placeholder: duplicate of TC-A102 (covered above)
})->skip('Placeholder: covered by TC-A102');

it('TC-A017 Send back a session log with reason', function (): void {
    // Placeholder: duplicate of TC-A103 (covered above)
})->skip('Placeholder: covered by TC-A103');

it('TC-A018 Cannot approve a DRAFT session log', function (): void {
    // Placeholder: DRAFT status Approve button visibility not yet covered
})->skip('Placeholder: DRAFT approval restriction not yet covered by browser automation');

it('TC-A019 Cannot approve already-approved log', function (): void {
    // Placeholder: idempotent approval restriction not yet covered
})->skip('Placeholder: already-approved restriction not yet covered');

it('TC-A020 Approve session log with minimum required fields', function (): void {
    // Placeholder: minimal-data approval scenario not yet covered
})->skip('Placeholder: minimal-data approval not yet covered');
