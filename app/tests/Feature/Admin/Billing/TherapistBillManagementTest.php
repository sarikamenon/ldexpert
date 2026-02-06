<?php

use App\Enums\SessionLogStatus;
use App\Enums\TherapistBillStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function billingAdminUser(): User
{
    return User::factory()->admin()->create();
}

function createApprovedSessionLogForBilling(User $therapist, User $student, School $school, Service $service, ServiceSupportAgreement $ssa): SessionLog
{
    return SessionLog::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'ssa_id' => $ssa->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_therapist' => true,
        'therapist_billable_amount' => 100.00,
        'therapist_bill_id' => null,
    ]);
}

beforeEach(function () {
    // Set up company settings
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');
});

it('allows admin to view therapist bills index', function () {
    $admin = billingAdminUser();
    TherapistBill::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.billing.therapist-bills.index'));

    $response->assertOk()
        ->assertSee('Therapist Bills')
        ->assertViewIs('admin.billing.therapist-bills.index')
        ->assertViewHas('bills');
});

it('prevents non-admin from accessing therapist bills', function () {
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.billing.therapist-bills.index'))
        ->assertForbidden();
});

it('allows admin to view therapist bill create page', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create();
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);

    $response = $this->actingAs($admin)
        ->get(route('admin.billing.therapist-bills.create'));

    $response->assertOk()
        ->assertSee('Create Therapist Bill')
        ->assertViewIs('admin.billing.therapist-bills.create')
        ->assertViewHas('sessionLogs');
});

it('creates a therapist bill from selected session logs', function () {
    Mail::fake();

    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create();
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $sessionLog1 = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);
    $sessionLog2 = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);

    $response = $this->actingAs($admin)
        ->post(route('admin.billing.therapist-bills.store'), [
            'therapist_id' => $therapist->id,
            'bill_date' => now()->format('Y-m-d'),
            'billing_period_start' => now()->subDays(30)->format('Y-m-d'),
            'billing_period_end' => now()->format('Y-m-d'),
            'session_log_ids' => [$sessionLog1->id, $sessionLog2->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('therapist_bills', [
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::DRAFT->value,
    ]);

    $bill = TherapistBill::where('therapist_id', $therapist->id)->first();
    expect($bill->sessionLogs)->toHaveCount(2);
    expect((float) $bill->subtotal)->toBe(200.00);
});

it('allows admin to view therapist bill details', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.billing.therapist-bills.show', $bill));

    $response->assertOk()
        ->assertSee($bill->bill_number)
        ->assertViewIs('admin.billing.therapist-bills.show');
});

it('allows admin to send therapist bill', function () {
    Mail::fake();

    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::DRAFT->value,
        'therapist_email' => 'therapist@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.billing.therapist-bills.send', $bill), [
            'email' => null,
            'message' => null,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Mail::assertSent(\App\Mail\TherapistBillMail::class);

    $bill->refresh();
    expect($bill->status)->toBe(TherapistBillStatus::SENT);
    expect($bill->sent_at)->not->toBeNull();
});
