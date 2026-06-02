<?php

use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
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
    $school = School::factory()->create();
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

it('creates an invoice from selected session logs', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create([
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

it('verifies snapshot data persists even if school changes', function () {
    $admin = invoiceAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create([
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
    $school = School::factory()->create();

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
    $school = School::factory()->create();
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
    $school = School::factory()->create();
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
    $school = School::factory()->create();
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
