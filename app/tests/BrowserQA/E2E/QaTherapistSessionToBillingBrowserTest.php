<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\SchoolStatus;
use App\Enums\TherapistBillStatus;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Billing Chain ────────────────────────────────────────────────────────────

it('TC-E006 session log billable amount flows correctly to therapist bill total', function (): void {
    $this->markTestSkipped(
        'Assumed flow not implemented: there is no one-step "Generate Bill" that auto-sums approved sessions. '
        . 'The create page (/admin/billing/therapist-bills/create, button "Create draft") only creates an EMPTY '
        . 'draft — TherapistBillService::generateBill bills only explicitly-passed session_log_ids. Approved '
        . 'sessions are attached on a separate attach-sessions page (checkboxes) which then sets total_due. '
        . 'This test needs rebuilding as that two-step flow.'
    );
});

it('TC-E007 paying a therapist bill creates a correct ledger entry', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $bill = TherapistBill::factory()->sent($admin)->create([
        'therapist_id' => $therapist->id,
        'total_due'    => 400.00,
    ]);

    // Record Payment opens an Alpine modal (fields: paid_at prefilled, amount,
    // method required). The opener only dispatches a window event.
    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/' . $bill->id)
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

it('TC-E008 cannot create a bill from an unapproved session log', function (): void {
    $this->markTestSkipped(
        'Assumed flow not implemented: there is no one-step "Generate Bill". Billing happens by creating an '
        . 'empty draft then attaching APPROVED sessions on the attach-sessions page (only approved logs appear '
        . 'as available). The real assertion ("a submitted-but-unapproved log is not offered for billing") '
        . 'belongs on that attach-sessions page and needs rebuilding there.'
    );
});

it('TC-E009 admin cannot record a second payment on an already-paid bill', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status'       => TherapistBillStatus::PAID,
        'total_due'    => 500.00,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/' . $bill->id)
            ->pause(800);

        // The Record Payment opener (type="button") only renders when the bill
        // is neither draft nor paid; the modal's submit is type="submit".
        $openers = $browser->script(
            "return Array.from(document.querySelectorAll('button[type=\"button\"]')).filter(b => b.textContent.trim() === 'Record Payment').length;"
        )[0];
        expect((int) $openers)->toBe(0);
    });
});

it('TC-E010 bill with multiple session logs totals all billable amounts correctly', function (): void {
    $this->markTestSkipped(
        'Assumed flow not implemented: same as TC-E006 — there is no one-step "Generate Bill" that auto-sums '
        . 'approved sessions. The create page only makes an empty draft; totals come from attaching sessions on '
        . 'the separate attach-sessions page. Needs rebuilding as the two-step create-draft → attach flow.'
    );
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
        . 'served_minutes; served_hours is a derived read-only accessor), and the therapist submit has no '
        . '"Yes, approve" confirmation dialog. Test assumes a data model and flow that do not exist.'
    );
});

it('TC-E018 end-to-end flow with invalid SSA allocation (zero hours) fails', function (): void {
    $this->markTestSkipped(
        'Not implemented as modeled: the admin SSA create form has no hours_allocated field/validation '
        . '(allocation is minutes-based via tho_minutes). There is no "zero hours" validation surface to assert.'
    );
});

it('TC-E019 end-to-end flow with session exactly matching SSA allocation', function (): void {
    $this->markTestSkipped(
        'Not implemented as modeled: relies on SSA hours_allocated/served_hours columns that do not exist and '
        . 'a "Yes, approve" submit confirmation that does not exist. See TC-E017.'
    );
});

it('TC-E020 two SSAs for the same student and therapist are both created', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $school    = School::factory()->qa()->create(['status' => SchoolStatus::ACTIVE]);
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();

    $ssa1 = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $ssa2 = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
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
        . 'a "Yes, approve" submit confirmation that does not exist. See TC-E017.'
    );
});
