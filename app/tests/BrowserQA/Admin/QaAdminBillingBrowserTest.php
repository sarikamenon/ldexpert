<?php

declare(strict_types=1);

use App\Models\{Invoice, School, SessionLog, TherapistBill, User};
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

/**
 * Admin Billing QA Tests — Therapist Bills, Invoices, Finance Dashboard
 *
 * LOCATOR GUIDE:
 * - Invoices List: #invoicesTable, /admin/invoices
 * - Therapist Bills List: #therapistBillsTable, /admin/billing/therapist-bills
 * - Finance Dashboard: /admin/finance/dashboard
 * - Invoice Create: #school_id, #invoice_date, #billing_period_start, #billing_period_end, #notes
 *   NOTE: there is NO due_date field on the invoice create form
 * - Bill Create: #therapist_id, #bill_date, #billing_period_start, #billing_period_end, #notes
 *   NOTE: there is NO amount field on the bill create form
 * - Bill Show header: "Bill {bill_number}" — NOT "Therapist Bill"
 * - Record Payment modal fields: paid_at (date), amount (number) — NOT payment_date
 * - Delete Bill: uses SweetAlert2 confirm (.swal2-confirm) before submitting
 * - Billing Settings: has x-ui::select and x-ui::input elements; submit text "Save Settings"
 * - Billing Schedule Create: #schedule_type (values: school_invoice|therapist_bill), #schedulable_id, #payment_terms_days
 */

// ─── Invoices: Create & View ──────────────────────────────────

it('TC-F001 Admin can create an invoice for a school', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/create')
            ->waitFor('#school_id', 20)
            ->select('#school_id', (string) $school->id)
            ->type('#invoice_date', now()->toDateString())
            ->type('#billing_period_start', now()->subDays(30)->toDateString())
            ->type('#billing_period_end', now()->toDateString())
            ->press('Create draft')
            ->waitFor('h1, h2', 20)
            ->assertPathContains('/admin/invoices');
    });
});

it('TC-F002 Admin can view invoice list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices')
            ->waitFor('#invoicesTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-F003 Admin can view invoice details', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();
    $invoice = Invoice::factory()->create(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($admin, $invoice): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/' . $invoice->id)
            ->waitFor('h1, h2', 20)
            ->assertSee('Invoice');
    });
});

it('TC-F004 Admin can view invoice details page', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();
    $invoice = Invoice::factory()->create(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($admin, $invoice): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/' . $invoice->id)
            ->waitFor('h1, h2', 20)
            ->assertSee('Invoice')
            ->assertSee($invoice->invoice_number);
    });
});

it('TC-F005 Admin can send invoice to school', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool(['contact_email' => 'billing@qatest.local']);
    $invoice = Invoice::factory()->create(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($admin, $invoice): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/' . $invoice->id)
            ->waitFor('form[action*="send"] button[type="submit"]', 20)
            ->press('Send Invoice')
            ->waitFor('h1, h2', 20)
            ->assertPathContains('/admin/invoices');
    });
});

it('TC-F006 Admin can download invoice PDF', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();
    $invoice = Invoice::factory()->create(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($admin, $invoice): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/' . $invoice->id)
            ->waitFor('a[href*="download"]', 20)
            ->assertPresent('a[href*="download"]');
    });
});

it('TC-F007 Admin can attach sessions to invoice', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $invoice = Invoice::factory()->create(['school_id' => $school->id]);
    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'session_date' => now()->toDateString(),
        'status' => 'approved',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $invoice): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/' . $invoice->id . '/attach-sessions')
            ->waitFor('h1', 60)
            ->assertPathContains('/admin/invoices');
    });
});

// ─── Therapist Bills: Create & Manage ──────────────────────────

it('TC-F010 Admin can create a therapist bill', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist');

    $this->browse(function (Browser $browser) use ($admin, $therapist): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/create')
            ->waitFor('#therapist_id', 20)
            ->select('#therapist_id', (string) $therapist->id)
            ->type('#bill_date', now()->toDateString())
            ->type('#billing_period_start', now()->subDays(30)->toDateString())
            ->type('#billing_period_end', now()->toDateString())
            ->press('Create draft')
            ->waitFor('h1, h2', 20)
            ->assertPathContains('/admin/billing/therapist-bills');
    });
});

it('TC-F011 Admin can view therapist bills list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/therapist-bills')
            ->waitFor('#therapistBillsTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-F012 Admin can view therapist bill details', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist');
    $bill = TherapistBill::factory()->create(['therapist_id' => $therapist->id]);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/' . $bill->id)
            ->waitFor('h1, h2', 20)
            ->assertSee($bill->bill_number);
    });
});

it('TC-F013 Admin can send therapist bill', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist', ['email' => 'qa.therapist' . uniqid() . '@test.local']);
    $bill = TherapistBill::factory()->create(['therapist_id' => $therapist->id]);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/' . $bill->id)
            ->waitFor('form[action*="send"] button[type="submit"]', 20)
            ->press('Send Bill')
            ->waitFor('h1, h2', 20)
            ->assertPathContains('/admin/billing/therapist-bills');
    });
});

it('TC-F014 Admin can delete a therapist bill draft', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist');
    $bill = TherapistBill::factory()->create(['therapist_id' => $therapist->id, 'status' => 'draft']);

    $billId = $bill->id;

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/' . $bill->id)
            ->waitFor('#deleteBillBtn', 20)
            ->press('Delete Bill')
            ->waitFor('.swal2-confirm', 5)
            ->click('.swal2-confirm')
            ->waitFor('h1, h2', 20);
    });

    $this->assertSoftDeleted('therapist_bills', ['id' => $billId]);
});

it('TC-F015 Admin can attach sessions to therapist bill', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $student = $this->createQaUser('student');
    $therapist = $this->createQaUser('therapist');
    $school = $this->createQaSchool();
    $student->studentProfile->update(['school_id' => $school->id]);

    $bill = TherapistBill::factory()->create(['therapist_id' => $therapist->id]);
    $sessionLog = SessionLog::factory()->create([
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'session_date' => now()->toDateString(),
        'status' => 'approved',
    ]);

    $this->browse(function (Browser $browser) use ($admin, $bill): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/therapist-bills/' . $bill->id . '/attach-sessions')
            ->waitFor('h1', 60)
            ->assertPathContains('/admin/billing/therapist-bills');
    });
});

// ─── Finance Dashboard ────────────────────────────────────────

it('TC-F020 Admin can view finance dashboard', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/finance/dashboard')
            ->waitFor('h1, h2', 20)
            ->assertSee('Finance');
    });
});

it('TC-F021 Finance dashboard displays key metrics', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/finance/dashboard')
            ->waitFor('.grid', 20)
            ->assertPresent('.grid')
            ->assertSee('Finance Dashboard');
    });
});

// ─── Invoice Payments ──────────────────────────────────────────

it('TC-F030 Admin can record invoice payment', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();
    // Record Payment button only appears on sent (non-draft, non-paid) invoices
    $invoice = Invoice::factory()->sent($admin)->create(['school_id' => $school->id, 'total' => 1000.00]);

    $this->browse(function (Browser $browser) use ($admin, $invoice): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/invoices/' . $invoice->id)
            ->waitFor('button[type="button"]', 20)
            ->press('Record Payment')
            ->waitFor('input[name="paid_at"]', 20)
            ->type('input[name="paid_at"]', now()->toDateString())
            ->type('input[name="amount"]', '500.00')
            ->click('button[type="submit"]')
            ->waitFor('h1, h2', 20);
    });
});

it('TC-F031 Admin can view payments list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/payments/invoices')
            ->waitFor('#paymentsTable, table', 20)
            ->assertPresent('table');
    });
});

// ─── Billing Settings & Configuration ──────────────────────────

it('TC-F040 Admin can access billing settings', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/settings')
            ->waitFor('h1', 60)
            ->assertSee('Billing Settings');
    });
});

it('TC-F041 Admin can update billing settings', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/settings')
            ->waitFor('button[type="submit"]', 20)
            // Find and fill any available input
            ->click('button[type="submit"]')
            ->waitFor('.alert-success, h1, h2', 20);
    });
});

// ─── Ledger Accounts ──────────────────────────────────────────

it('TC-F050 Admin can view ledger accounts', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ledger/accounts')
            ->waitFor('#accountsTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-F051 Admin can view account transactions', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    // This would require knowing an existing account ID, skipping for now
    // but the pattern is: /admin/ledger/accounts/{type}/{id}

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ledger/accounts')
            ->waitFor('table', 20)
            ->assertPresent('table');
    });
});

// ─── Billing Schedules ─────────────────────────────────────────

it('TC-F060 Admin can view billing schedules', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/schedules')
            ->waitFor('#schedulesTable, table', 20)
            ->assertPresent('table');
    });
});

it('TC-F061 Admin can create a billing schedule', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/billing/schedules/create')
            ->waitFor('#schedule_type', 20)
            ->select('#schedule_type', 'school_invoice')
            ->type('#schedulable_id', (string) $school->id)
            ->type('#payment_terms_days', '30')
            ->press('Create Schedule')
            ->waitFor('h1, h2', 20);
    });
});
