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

    $response = $this->actingAs($admin)
        ->get(route('admin.billing.therapist-bills.create'));

    $response->assertOk()
        ->assertSee('Create Therapist Bill')
        ->assertViewIs('admin.billing.therapist-bills.create')
        ->assertViewHas('therapists')
        ->assertViewHas('billNumber');
});

it('creates draft with no sessions and redirects to attach-sessions', function () {
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

    $response = $this->actingAs($admin)
        ->post(route('admin.billing.therapist-bills.store'), [
            'therapist_id' => $therapist->id,
            'bill_date' => now()->format('Y-m-d'),
            'billing_period_start' => now()->subDays(30)->format('Y-m-d'),
            'billing_period_end' => now()->format('Y-m-d'),
            'session_log_ids' => [],
        ]);

    $response->assertRedirect();
    expect(str_contains($response->headers->get('Location'), 'attach-sessions'))->toBeTrue();

    $bill = TherapistBill::where('therapist_id', $therapist->id)->latest()->first();
    expect($bill)->not->toBeNull();
    expect($bill->status)->toBe(TherapistBillStatus::DRAFT);
    expect((float) $bill->subtotal)->toBe(0.0);
    expect((float) $bill->total_due)->toBe(0.0);
    expect($bill->sessionLogs->count())->toBe(0);
});

it('attach-sessions page shows for draft and updates sessions', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create();
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log1 = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);
    $log2 = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::DRAFT->value,
        'billing_period_start' => now()->subDays(30),
        'billing_period_end' => now(),
        'bill_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 0,
        'adjustments_total' => 0,
        'total_due' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.billing.therapist-bills.attach-sessions.store', $bill), [
            'session_log_ids' => [$log1->id, $log2->id],
        ]);

    $response->assertRedirect(route('admin.billing.therapist-bills.show', $bill));
    $response->assertSessionHas('success');

    $bill->refresh();
    expect($bill->sessionLogs->count())->toBe(2);
    expect((float) $bill->subtotal)->toBe(200.0);
    expect($log1->fresh()->therapist_bill_id)->toBe($bill->id)
        ->and($log2->fresh()->therapist_bill_id)->toBe($bill->id);
});

it('attach-sessions allows removing sessions', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create();
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $log1 = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);
    $log2 = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::DRAFT->value,
    ]);

    $log1->update(['therapist_bill_id' => $bill->id]);
    $log2->update(['therapist_bill_id' => $bill->id]);

    $response = $this->actingAs($admin)
        ->post(route('admin.billing.therapist-bills.attach-sessions.store', $bill), [
            'session_log_ids' => [$log1->id],
        ]);

    $response->assertRedirect(route('admin.billing.therapist-bills.show', $bill));

    $bill->refresh();
    expect($bill->sessionLogs->count())->toBe(1)
        ->and((float) $bill->subtotal)->toBe(100.0);

    expect($log2->fresh()->therapist_bill_id)->toBeNull();
});

it('attach-sessions rejects non-draft therapist bill', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::SENT->value,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.billing.therapist-bills.attach-sessions', $bill));

    $response->assertForbidden();
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

it('allows admin to delete a draft bill and unlinks sessions', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create();
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::DRAFT->value,
    ]);

    $session = createApprovedSessionLogForBilling($therapist, $student, $school, $service, $ssa);
    $session->update(['therapist_bill_id' => $bill->id]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.billing.therapist-bills.destroy', $bill));

    $response->assertRedirect(route('admin.billing.therapist-bills.index'));
    $response->assertSessionHas('success');

    expect(TherapistBill::find($bill->id))->toBeNull();
    expect(TherapistBill::withTrashed()->find($bill->id))->not->toBeNull();
    expect($session->fresh()->therapist_bill_id)->toBeNull();
});

it('allows admin to delete a sent bill', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::SENT->value,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.billing.therapist-bills.destroy', $bill));

    $response->assertRedirect(route('admin.billing.therapist-bills.index'));
    expect(TherapistBill::withTrashed()->find($bill->id)->deleted_at)->not->toBeNull();
});

it('prevents admin from deleting a paid bill', function () {
    $admin = billingAdminUser();
    $therapist = User::factory()->therapist()->create();

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::PAID->value,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.billing.therapist-bills.destroy', $bill));

    $response->assertForbidden();
    expect(TherapistBill::find($bill->id))->not->toBeNull();
});

it('prevents therapist from deleting a bill', function () {
    $therapist = User::factory()->therapist()->create();

    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
        'status' => TherapistBillStatus::DRAFT->value,
    ]);

    $response = $this->actingAs($therapist)
        ->delete(route('admin.billing.therapist-bills.destroy', $bill));

    $response->assertForbidden();
    expect(TherapistBill::find($bill->id))->not->toBeNull();
});
