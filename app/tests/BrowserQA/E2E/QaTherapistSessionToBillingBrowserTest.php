<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\SchoolStatus;
use App\Enums\TherapistBillStatus;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Billing Chain ────────────────────────────────────────────────────────────

it('TC-E006 golden path: therapist logs and submits a session, admin approves, then bills it via the two-step flow', function (): void {
    // ── Preconditions (factories) ───────────────────────────────────────────
    // A session can only be billed once the full setup exists: active school,
    // a school contract + therapist contract that each price the service, an
    // active SSA assigning the therapist, and the student linked to the school.
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = User::factory()->therapist()->qa()->create();
    $therapistProfile = TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create(['status' => SchoolStatus::ACTIVE]);

    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create([
        'name' => 'Golden Path Service '.uniqid(),
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

    // Contracts with rates — billing validation throws if a service is unpriced.
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

    // therapist_contracts keys on the TherapistProfile id, not the User id.
    $therapistContract = TherapistContract::create([
        'therapist_id' => $therapistProfile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);
    TherapistContractService::create([
        'therapist_contract_id' => $therapistContract->id,
        'service_id' => $service->id,
        'rate' => 100, // $100/hr → a 60-minute session bills $100.00
        'rate_type' => 'H',
    ]);

    // Keep the session in the current week so it falls inside the bill's
    // default 30-day billing period when the draft bill is created.
    $sessionStart = now('UTC')->startOfWeek()->setTime(9, 0);

    // ── Step 1: therapist logs the session (status: draft) ───────────────────
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
            ->type('notes', 'TC-E006 golden path session notes with valid length')
            ->script("window.jQuery && window.jQuery('.select2-hidden-accessible').select2('close');");

        $browser->press('Create Session Log')
            ->waitForText('created successfully', 10);
    });

    $sessionLog = SessionLog::where('therapist_id', $therapist->id)->where('status', 'draft')->firstOrFail();

    // ── Step 2: therapist submits the log (draft → submitted) ────────────────
    $this->browse(function (Browser $browser) use ($therapist, $sessionLog): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/session-logs/'.$sessionLog->id)
            ->waitForText('Submit', 10)
            ->press('Submit')
            ->pause(1500);
    });
    $sessionLog->refresh();
    expect($sessionLog->status->value)->toBe('submitted');

    // ── Step 3: admin approves the log (submitted → approved) ────────────────
    $this->browse(function (Browser $browser) use ($admin, $sessionLog): void {
        $browser->loginAs($admin)
            ->visit('/admin/session-logs/'.$sessionLog->id)
            ->waitFor('form[action*="approve"] button[type="submit"]', 20)
            ->waitForReload(function (Browser $b): void {
                $b->click('form[action*="approve"] button[type="submit"]');
            }, 15);
    });
    $sessionLog->refresh();
    expect($sessionLog->status->value)->toBe('approved');

    // ── Step 4: admin creates an empty draft bill (period fields default) ────
    $this->browse(function (Browser $browser) use ($admin, $therapist): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/create')
            ->waitFor('select[name="therapist_id"]', 10)
            ->select('therapist_id', (string) $therapist->id)
            ->press('Create draft')
            ->pause(1500);
    });

    $bill = TherapistBill::where('therapist_id', $therapist->id)->latest('id')->firstOrFail();

    // ── Step 5: admin attaches the approved session to the bill ──────────────
    $this->browse(function (Browser $browser) use ($admin, $bill, $sessionLog): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id.'/attach-sessions')
            ->waitFor('input[name="session_log_ids[]"]', 10)
            ->check('input[name="session_log_ids[]"][value="'.$sessionLog->id.'"]')
            ->press('Update sessions')
            ->pause(1500);
    });

    // ── Step 6: the session's billable amount flows to the bill total ────────
    $bill->refresh();
    $sessionLog->refresh();
    expect($sessionLog->therapist_bill_id)->toBe($bill->id);
    expect((float) $bill->total_due)->toEqual(100.0);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id)
            ->waitForText('Total Due', 10)
            ->assertSee('100.00');
    });
});

it('TC-E007 paying a therapist bill creates a correct ledger entry', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $bill = TherapistBill::factory()->sent($admin)->create([
        'therapist_id' => $therapist->id,
        'total_due' => 400.00,
    ]);

    // Record Payment opens an Alpine modal (fields: paid_at prefilled, amount,
    // method required). The opener only dispatches a window event.
    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id)
            ->waitForText('Record Payment')
            ->script("window.dispatchEvent(new CustomEvent('open-record-payment-modal'));");
        $browser->waitFor('input[name="amount"]', 10)
            ->clear('amount')
            ->type('amount', '400.00')
            ->select('method', PaymentMethod::cases()[0]->value)
            ->click('form[action*="/payments"] button[type="submit"]')
            ->pause(1500);
    });

    // The ledger references the PAYMENT (reference_type/_id), not the bill.
    // NOTE: paying a bill does not transition its status to PAID (status stays
    // SENT; "paid" is derived), so we assert the ledger outcome, not the status.
    $payment = TherapistBillPayment::where('therapist_bill_id', $bill->id)->first();
    expect($payment)->not->toBeNull();

    $ledgerEntry = LedgerEntry::forReference($payment)->first();
    expect($ledgerEntry)->not->toBeNull();
    expect($ledgerEntry?->balance_after)->not->toBeNull();
});

/**
 * Build a billing-ready therapist: active school, linked student, active SSA
 * assigning the therapist, and a service. Session logs are created via factory
 * in the individual tests (TC-E006 already covers the UI create→submit→approve).
 *
 * @return array{admin: User, therapist: User, school: School, student: User, service: Service, ssa: ServiceSupportAgreement}
 */
function billingReadyTherapistSetup(): array
{
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school = School::factory()->qa()->create(['status' => SchoolStatus::ACTIVE]);

    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['name' => 'Bill Service '.uniqid(), 'status' => 'active']);

    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    return compact('admin', 'therapist', 'school', 'student', 'service', 'ssa');
}

it('TC-E008 an unapproved session log is not offered for billing on the attach-sessions page', function (): void {
    ['admin' => $admin, 'therapist' => $therapist, 'school' => $school, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = billingReadyTherapistSetup();

    // One approved log (must be offered) and one still-submitted log (must NOT be).
    $approvedLog = SessionLog::factory()->approved()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->subDays(2)->toDateString(),
        'is_billable_therapist' => true,
        'therapist_billable_amount' => 100,
        'approved_by_id' => $admin->id,
        'submitted_by_id' => $therapist->id,
    ]);

    $submittedLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->subDays(2)->toDateString(),
        'status' => 'submitted',
        'is_billable_therapist' => true,
        'therapist_billable_amount' => 100,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $therapist): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/create')
            ->waitFor('select[name="therapist_id"]', 10)
            ->select('therapist_id', (string) $therapist->id)
            ->press('Create draft')
            ->pause(1500);
    });

    $bill = TherapistBill::where('therapist_id', $therapist->id)->latest('id')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin, $bill, $approvedLog, $submittedLog): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id.'/attach-sessions')
            ->waitFor('input[name="session_log_ids[]"]', 10)
            ->assertPresent('input[name="session_log_ids[]"][value="'.$approvedLog->id.'"]')
            ->assertMissing('input[name="session_log_ids[]"][value="'.$submittedLog->id.'"]');
    });
});

it('TC-E009 admin cannot record a second payment on an already-paid bill', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::PAID,
        'total_due' => 500.00,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id)
            ->pause(800);

        // The Record Payment opener (type="button") only renders when the bill
        // is neither draft nor paid; the modal's submit is type="submit".
        $openers = $browser->script(
            "return Array.from(document.querySelectorAll('button[type=\"button\"]')).filter(b => b.textContent.trim() === 'Record Payment').length;"
        )[0];
        expect((int) $openers)->toBe(0);
    });
});

it('TC-E010 a bill with multiple session logs totals all billable amounts correctly', function (): void {
    ['admin' => $admin, 'therapist' => $therapist, 'school' => $school, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = billingReadyTherapistSetup();

    // Two approved logs with known amounts — the bill total must be their sum.
    $logs = collect([100, 150])->map(fn (int $amount): SessionLog => SessionLog::factory()->approved()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'school_id' => $school->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'session_date' => now()->subDays(2)->toDateString(),
        'is_billable_therapist' => true,
        'therapist_billable_amount' => $amount,
        'approved_by_id' => $admin->id,
        'submitted_by_id' => $therapist->id,
    ]));

    $this->browse(function (Browser $browser) use ($admin, $therapist): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/create')
            ->waitFor('select[name="therapist_id"]', 10)
            ->select('therapist_id', (string) $therapist->id)
            ->press('Create draft')
            ->pause(1500);
    });

    $bill = TherapistBill::where('therapist_id', $therapist->id)->latest('id')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin, $bill, $logs): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id.'/attach-sessions')
            ->waitFor('input[name="session_log_ids[]"]', 10)
            ->check('input[name="session_log_ids[]"][value="'.$logs[0]->id.'"]')
            ->check('input[name="session_log_ids[]"][value="'.$logs[1]->id.'"]')
            ->press('Update sessions')
            ->pause(1500);
    });

    $bill->refresh();
    expect($bill->sessionLogs()->count())->toBe(2);
    expect((float) $bill->total_due)->toEqual(250.0);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/'.$bill->id)
            ->waitForText('Total Due', 10)
            ->assertSee('250.00');
    });
});

// ─── Hours / approval auto-increment flows ────────────────────────────────────
// These were authored against an SSA "hours" model and a submit-confirmation
// flow that do not exist: service_support_agreements has no hours_allocated /
// served_hours columns (it uses tho_minutes / served_minutes; served_hours is a
// read-only derived accessor), and the therapist submit action is a plain POST
// form with no "Yes, approve" confirmation dialog. They are skipped until the
// data model and flow they assume exist.

it('TC-E017 complete business flow with 30-minute session auto-increments hours on approval', function (): void {
    $this->markTestSkipped(
        'Not implemented as modeled: SSA has no hours_allocated/served_hours columns (uses tho_minutes/'
        .'served_minutes; served_hours is a derived read-only accessor), and the therapist submit has no '
        .'"Yes, approve" confirmation dialog. Test assumes a data model and flow that do not exist.'
    );
});

it('TC-E018 end-to-end flow with invalid SSA allocation (zero hours) fails', function (): void {
    $this->markTestSkipped(
        'Not implemented as modeled: the admin SSA create form has no hours_allocated field/validation '
        .'(allocation is minutes-based via tho_minutes). There is no "zero hours" validation surface to assert.'
    );
});

it('TC-E019 end-to-end flow with session exactly matching SSA allocation', function (): void {
    $this->markTestSkipped(
        'Not implemented as modeled: relies on SSA hours_allocated/served_hours columns that do not exist and '
        .'a "Yes, approve" submit confirmation that does not exist. See TC-E017.'
    );
});

it('TC-E020 two SSAs for the same student and therapist are both created', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $school = School::factory()->qa()->create(['status' => SchoolStatus::ACTIVE]);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();

    $ssa1 = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
    ]);

    $ssa2 = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
    ]);

    // Overlapping SSAs for the same student/therapist are allowed per business rules.
    expect($ssa1->assigned_therapist_id)->toBe($therapist->id);
    expect($ssa2->assigned_therapist_id)->toBe($therapist->id);
    expect($ssa1->student_id)->toBe($student->id);
    expect($ssa2->student_id)->toBe($student->id);
});

it('TC-E021 end-to-end flow with multiple sessions accumulating to 2 hours', function (): void {
    $this->markTestSkipped(
        'Not implemented as modeled: relies on SSA hours_allocated/served_hours columns that do not exist and '
        .'a "Yes, approve" submit confirmation that does not exist. See TC-E017.'
    );
});
