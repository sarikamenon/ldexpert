<?php

use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
use App\Models\BillingSchedule;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function invoiceAdminUser(): User
{
    return User::factory()->admin()->create();
}

function createApprovedSessionLog(User $therapist, User $student, School $school, Service $service, ServiceSupportAgreement $ssa): SessionLog
{
    return SessionLog::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'ssa_id' => $ssa->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 100.00,
        'invoice_id' => null,
    ]);
}

beforeEach(function () {
    // Set up company settings
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');
});

it('allows admin to view invoices index', function () {
    $admin = invoiceAdminUser();
    Invoice::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.invoices.index'));

    $response->assertOk()
        ->assertSee('Invoices')
        ->assertViewIs('admin.invoices.index')
        ->assertViewHas('invoices');
});

it('prevents non-admin from accessing invoices', function () {
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.invoices.index'))
        ->assertForbidden();
});

it('allows admin to view invoice create page', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create(['is_private_student' => false]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $response = $this->actingAs($admin)
        ->get(route('admin.invoices.create'));

    $response->assertOk()
        ->assertSee('Create Invoice')
        ->assertViewIs('admin.invoices.create')
        ->assertViewHas('schools')
        ->assertViewHas('invoiceNumber');
});

it('renders each school option with its resolved payment-terms days', function () {
    $admin = invoiceAdminUser();

    // Distinct defaults so the schedule / standard-fallback branches are visible.
    BillingSetting::getSettings()->update([
        'standard_default_payment_terms_days' => 45,
    ]);

    $withSchedule = School::factory()->create([
        'is_private_student' => false,
        'display_name' => 'Scheduled School',
    ]);
    BillingSchedule::factory()->forSchool($withSchedule)->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'billing_mode' => BillingMode::STANDARD->value,
        'payment_terms_days' => 7,
    ]);

    $noSchedule = School::factory()->create([
        'is_private_student' => false,
        'display_name' => 'Fallback School',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.invoices.create'));

    // School with a school_invoice schedule shows its own terms; the no-schedule
    // school falls back to the standard default, not the legacy generic default.
    $response->assertOk()
        ->assertSee('data-payment-terms-days="7"', false)
        ->assertSee('data-payment-terms-days="45"', false);
});

it('creates an invoice from selected session logs', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create([
        'is_private_student' => false,
        'full_name' => 'Test School Full',
        'display_name' => 'Test School',
        'contact_email' => 'contact@school.com',
        'invoice_email' => 'billing@school.com',
    ]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log1 = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);
    $log2 = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $payload = [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'session_log_ids' => [$log1->id, $log2->id],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.store'), $payload)
        ->assertRedirect();

    $response->assertSessionHas('success', 'Invoice created successfully.');

    // Verify invoice was created with snapshot data
    $invoice = Invoice::where('school_id', $school->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->school_name)->toBe('Test School Full')
        ->and($invoice->school_display_name)->toBe('Test School')
        ->and($invoice->school_contact_email)->toBe('contact@school.com')
        ->and($invoice->school_invoice_email)->toBe('billing@school.com')
        ->and($invoice->company_name)->toBe('LD Expert LLP')
        ->and($invoice->company_address)->toBe('123 Company St')
        ->and($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and((float) $invoice->total)->toBe(200.00);

    // Verify session logs are linked
    expect($log1->fresh()->invoice_id)->toBe($invoice->id)
        ->and($log2->fresh()->invoice_id)->toBe($invoice->id);
});

it('derives the standard invoice due date from the invoice date, not today', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create(['is_private_student' => false, 'state' => 'CA']);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    // Backdate the invoice date a couple of months in the past.
    $invoiceDate = now()->subMonths(2)->startOfMonth();

    $this->actingAs($admin)
        ->post(route('admin.invoices.store'), [
            'school_id' => $school->id,
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'billing_period_start' => $invoiceDate->copy()->startOfMonth()->format('Y-m-d'),
            'billing_period_end' => $invoiceDate->copy()->endOfMonth()->format('Y-m-d'),
            'session_log_ids' => [$log->id],
        ])
        ->assertRedirect();

    $invoice = Invoice::where('school_id', $school->id)->first();

    // due_date = invoice_date + 30 days, NOT now() + 30 days.
    expect($invoice->due_date->toDateString())
        ->toBe($invoiceDate->copy()->addDays(30)->toDateString());
});

it('honours a user-supplied due date over the payment-terms default', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create(['is_private_student' => false, 'state' => 'CA']);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $invoiceDate = now()->startOfMonth();
    $customDueDate = $invoiceDate->copy()->addDays(7);

    $this->actingAs($admin)
        ->post(route('admin.invoices.store'), [
            'school_id' => $school->id,
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'due_date' => $customDueDate->format('Y-m-d'),
            'billing_period_start' => $invoiceDate->copy()->startOfMonth()->format('Y-m-d'),
            'billing_period_end' => $invoiceDate->copy()->endOfMonth()->format('Y-m-d'),
            'session_log_ids' => [$log->id],
        ])
        ->assertRedirect();

    $invoice = Invoice::where('school_id', $school->id)->first();

    expect($invoice->due_date->toDateString())->toBe($customDueDate->toDateString());
});

it('rejects a due date earlier than the invoice date', function () {
    $admin = invoiceAdminUser();
    $school = School::factory()->create(['is_private_student' => false, 'state' => 'CA']);

    $invoiceDate = now()->startOfMonth();

    $this->actingAs($admin)
        ->post(route('admin.invoices.store'), [
            'school_id' => $school->id,
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'due_date' => $invoiceDate->copy()->subDay()->format('Y-m-d'),
            'billing_period_start' => $invoiceDate->copy()->startOfMonth()->format('Y-m-d'),
            'billing_period_end' => $invoiceDate->copy()->endOfMonth()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('due_date');
});

it('creates an invoice for a school with an international 3-letter state code', function () {
    // Regression: invoices.school_state was varchar(2) while schools.state is
    // varchar(50), so snapshotting an international state (e.g. ISB) overflowed.
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create([
        'is_private_student' => false,
        'state' => 'ISB',
    ]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.store'), [
            'school_id' => $school->id,
            'invoice_date' => now()->format('Y-m-d'),
            'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
            'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
            'session_log_ids' => [$log->id],
        ])
        ->assertRedirect();

    $response->assertSessionHas('success', 'Invoice created successfully.');

    $invoice = Invoice::where('school_id', $school->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->school_state)->toBe('ISB');
});

it('verifies snapshot data persists even if school changes', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create([
        'is_private_student' => false,
        'full_name' => 'Original School Name',
        'display_name' => 'Original Display',
    ]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $payload = [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'session_log_ids' => [$log->id],
    ];

    $this->actingAs($admin)
        ->post(route('admin.invoices.store'), $payload);

    $invoice = Invoice::where('school_id', $school->id)->first();
    expect($invoice)->not->toBeNull();

    // Change school name
    $school->update([
        'full_name' => 'Changed School Name',
        'display_name' => 'Changed Display',
    ]);

    // Verify invoice still has original snapshot
    $invoice->refresh();
    expect($invoice->school_name)->toBe('Original School Name')
        ->and($invoice->school_display_name)->toBe('Original Display');
});

it('allows admin to view invoice details', function () {
    $admin = invoiceAdminUser();
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.invoices.show', $invoice));

    $response->assertOk()
        ->assertSee($invoice->invoice_number)
        ->assertViewIs('admin.invoices.show')
        ->assertViewHas('invoice');
});

it('sends invoice via email', function () {
    Mail::fake();

    $admin = invoiceAdminUser();
    $school = School::factory()->create([
        'invoice_email' => 'billing@school.com',
    ]);
    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'status' => InvoiceStatus::DRAFT->value,
        'school_invoice_email' => 'billing@school.com',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.send', $invoice), [
            'message' => 'Custom message',
        ])
        ->assertRedirect();

    $response->assertSessionHas('success', 'Invoice sent successfully.');

    Mail::assertSent(\App\Mail\InvoiceMail::class, function ($mail) use ($invoice) {
        return $mail->invoice->id === $invoice->id;
    });

    // Verify invoice status updated
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::SENT)
        ->and($invoice->fresh()->sent_at)->not->toBeNull()
        ->and($invoice->fresh()->sent_by_id)->toBe($admin->id);
});

it('sends a draft with a missing email snapshot using the address supplied on send, backfilling the school', function () {
    Mail::fake();

    $admin = invoiceAdminUser();
    // School has no invoice email — the very case that produced a null snapshot.
    $school = School::factory()->create(['invoice_email' => null]);
    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'status' => InvoiceStatus::DRAFT->value,
        'school_invoice_email' => null,
        'total' => 100.00,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.send', $invoice), [
            'email' => 'typed@school.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Invoice sent successfully. Saved this email to the school/family for future invoices.');

    Mail::assertSent(\App\Mail\InvoiceMail::class, function ($mail) {
        return $mail->hasTo('typed@school.com');
    });

    // Persisted onto the invoice snapshot AND backfilled onto the school.
    expect($invoice->fresh()->school_invoice_email)->toBe('typed@school.com')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::SENT)
        ->and($school->fresh()->invoice_email)->toBe('typed@school.com');
});

it('does not overwrite a school that already has an invoice email when sending', function () {
    Mail::fake();

    $admin = invoiceAdminUser();
    $school = School::factory()->create(['invoice_email' => 'school@existing.com']);
    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'status' => InvoiceStatus::DRAFT->value,
        'school_invoice_email' => 'school@existing.com',
        'total' => 100.00,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.send', $invoice), [
            'email' => 'oneoff@override.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Invoice sent successfully.');

    Mail::assertSent(\App\Mail\InvoiceMail::class, function ($mail) {
        return $mail->hasTo('oneoff@override.com');
    });

    // The one-off override lands on the invoice snapshot but never clobbers the school.
    expect($invoice->fresh()->school_invoice_email)->toBe('oneoff@override.com')
        ->and($school->fresh()->invoice_email)->toBe('school@existing.com');
});

it('persists a corrected email onto the snapshot when resending', function () {
    Mail::fake();

    $admin = invoiceAdminUser();
    $school = School::factory()->create(['invoice_email' => 'old@school.com']);
    $invoice = Invoice::factory()->sent()->create([
        'school_id' => $school->id,
        'school_invoice_email' => 'old@school.com',
        'total' => 100.00,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.resend-email', $invoice), [
            'email' => 'corrected@school.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Invoice email resent successfully.');

    Mail::assertSent(\App\Mail\InvoiceMail::class, function ($mail) {
        return $mail->hasTo('corrected@school.com');
    });

    // The snapshot updates; the school already had an email, so it is left intact.
    expect($invoice->fresh()->school_invoice_email)->toBe('corrected@school.com')
        ->and($school->fresh()->invoice_email)->toBe('old@school.com');
});

it('shows the send modal with a recipient email field on a draft invoice', function () {
    $admin = invoiceAdminUser();
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::DRAFT->value,
        'school_invoice_email' => null,
        'total' => 100.00,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('open-send-email-modal')
        ->assertSee('id="send-email-form"', false)
        ->assertSee('name="email"', false);
});

it('prevents admin from sending zero amount invoice', function () {
    Mail::fake();

    $admin = invoiceAdminUser();
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::DRAFT->value,
        'subtotal' => 0,
        'tax_total' => 0,
        'total' => 0,
        'school_invoice_email' => 'billing@school.com',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.send', $invoice), [
            'message' => null,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['error' => 'Zero amount invoices cannot be sent.']);
    Mail::assertNothingSent();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->sent_at)->toBeNull();
});

it('allows admin to download invoice PDF', function () {
    $admin = invoiceAdminUser();
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.invoices.download', $invoice));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('creates draft with no sessions and redirects to attach-sessions', function () {
    $admin = invoiceAdminUser();
    $school = School::factory()->create(['is_private_student' => false]);

    $payload = [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'session_log_ids' => [],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.store'), $payload);

    $response->assertRedirect();
    expect(str_contains($response->headers->get('Location'), 'attach-sessions'))->toBeTrue();

    $invoice = Invoice::where('school_id', $school->id)->latest()->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and((float) $invoice->total)->toBe(0.0)
        ->and($invoice->sessionLogs->count())->toBe(0);
});

it('attach-sessions page shows for draft and updates sessions', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create(['is_private_student' => false]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log1 = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);
    $log2 = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'status' => InvoiceStatus::DRAFT->value,
        'subtotal' => 0,
        'tax_total' => 0,
        'total' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.attach-sessions.store', $invoice), [
            'session_log_ids' => [$log1->id, $log2->id],
        ]);

    $response->assertRedirect(route('admin.invoices.show', $invoice));
    $response->assertSessionHas('success');

    $invoice->refresh();
    expect($invoice->sessionLogs->count())->toBe(2)
        ->and((float) $invoice->total)->toBe(200.0);
    expect($log1->fresh()->invoice_id)->toBe($invoice->id)
        ->and($log2->fresh()->invoice_id)->toBe($invoice->id);
});

it('attach-sessions allows removing sessions', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create(['is_private_student' => false]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log1 = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);
    $log2 = createApprovedSessionLog($therapist, $student, $school, $service, $ssa);

    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'status' => InvoiceStatus::DRAFT->value,
        'subtotal' => 200,
        'tax_total' => 0,
        'total' => 200,
    ]);
    $log1->update(['invoice_id' => $invoice->id]);
    $log2->update(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.attach-sessions.store', $invoice), [
            'session_log_ids' => [$log1->id],
        ]);

    $response->assertRedirect(route('admin.invoices.show', $invoice));
    $invoice->refresh();
    expect($invoice->sessionLogs->count())->toBe(1)
        ->and((float) $invoice->total)->toBe(100.0);
    expect($log2->fresh()->invoice_id)->toBeNull();
});

it('attach-sessions rejects non-draft invoice', function () {
    $admin = invoiceAdminUser();
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::SENT->value]);

    $response = $this->actingAs($admin)
        ->get(route('admin.invoices.attach-sessions', $invoice));

    $response->assertForbidden();
});

it('prevents creating invoice with already invoiced session logs', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create(['is_private_student' => false]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $existingInvoice = Invoice::factory()->create();
    $log = SessionLog::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'ssa_id' => $ssa->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'invoice_id' => $existingInvoice->id, // Already invoiced
    ]);

    $payload = [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'session_log_ids' => [$log->id],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.invoices.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('renders the invoice email with a date-range subject, new payment copy, and the invoice number in the summary', function () {
    $invoice = Invoice::factory()->create([
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'invoice_number' => 'INV-20260610-003',
        'subtotal' => 60.00,
        'total' => 60.00,
    ]);

    $mail = new \App\Mail\InvoiceMail($invoice, null, 'https://pay.example.com/abc');

    // The subject stays date-range only; the number lives in the body summary.
    expect($mail->envelope()->subject)->toBe('Invoice - June 1 - June 30');

    $html = $mail->render();

    expect($html)
        ->toContain('info@ldexpert.org')
        ->toContain('@StephanieTsapakis')
        ->toContain('706 Mesa Ridge, San Antonio, TX 78258')
        ->toContain('Warmly,')
        ->toContain('The LD Expert Team')
        ->toContain('The invoice includes 0 session(s) totaling')
        ->toContain('INV-20260610-003')
        // The date-range heading was removed from the body.
        ->not->toContain('<h1');
});

it('counts line items, not session logs, on an advance invoice email', function () {
    $invoice = Invoice::factory()->create([
        'billing_mode' => \App\Enums\BillingMode::ADVANCE->value,
        'subtotal' => 60.00,
        'total' => 60.00,
    ]);

    \App\Models\InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'line_type' => \App\Enums\InvoiceLineType::ADVANCE_SCHEDULED->value,
        'description' => 'Speech therapy — weekly',
        'billing_period_start' => $invoice->billing_period_start,
        'billing_period_end' => $invoice->billing_period_end,
        'quantity' => 1,
        'unit_price' => 60.00,
        'total' => 60.00,
        'sort_order' => 0,
    ]);

    $html = (new \App\Mail\InvoiceMail($invoice))->render();

    expect($html)->toContain('The invoice includes 1 scheduled session(s) totaling');
});

it('renders the reminder email aligned with the main invoice email (shared contact/sign-off, number in summary)', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'INV-20260610-009',
        'due_date' => '2026-07-09',
        'total' => 60.00,
    ]);

    $mail = new \App\Mail\InvoiceReminderMail($invoice, 'https://pay.example.com/abc');

    expect($mail->envelope()->subject)->toBe('Payment Reminder — Due Jul 09, 2026');
    expect($mail->envelope()->from?->address)->toBe('info@ldexpert.org');

    $html = $mail->render();

    expect($html)
        ->toContain('info@ldexpert.org')
        ->toContain('@StephanieTsapakis')
        ->toContain('706 Mesa Ridge, San Antonio, TX 78258')
        ->toContain('Warmly,')
        ->toContain('The LD Expert Team')
        ->toContain('INV-20260610-009');
});

it('renders the overdue email aligned with the main invoice email (shared contact/sign-off, number in summary)', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'INV-20260610-010',
        'due_date' => '2026-07-09',
        'total' => 60.00,
    ]);

    $mail = new \App\Mail\InvoiceOverdueMail($invoice, 5, 'https://pay.example.com/abc');

    expect($mail->envelope()->subject)->toBe('Overdue Payment — 5 Days Past Due');
    expect($mail->envelope()->from?->address)->toBe('info@ldexpert.org');

    $html = $mail->render();

    expect($html)
        ->toContain('info@ldexpert.org')
        ->toContain('@StephanieTsapakis')
        ->toContain('Warmly,')
        ->toContain('The LD Expert Team')
        ->toContain('INV-20260610-010');
});
