<?php

declare(strict_types=1);

use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\AdvanceReconciliation;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');
    User::factory()->admin()->create();
    Carbon::setTestNow('2026-06-10 02:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the command reconciles advance school schedules and ignores standard + therapist schedules', function () {
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
    BillingSchedule::factory()->forSchool($school)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'payment_terms_days' => 30,
    ]);

    // A standard school schedule + a therapist schedule that must be ignored.
    $standardSchool = School::factory()->create(['is_private_student' => false, 'state' => 'CA']);
    BillingSchedule::factory()->forSchool($standardSchool)->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'billing_mode' => BillingMode::STANDARD->value,
    ]);
    BillingSchedule::factory()->forTherapist()->create();

    // A late-approved billable May session for the advance school.
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    SessionLog::factory()->create([
        'school_id' => $school->id,
        'schedule_id' => $schedule->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 100.0,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
        'session_date' => '2026-05-12',
    ]);

    $this->artisan('billing:reconcile-advance')->assertSuccessful();

    // Exactly one reconciliation (the advance school), with a settlement invoice.
    expect(AdvanceReconciliation::count())->toBe(1);
    $settlement = Invoice::query()
        ->where('billing_mode', BillingMode::ADVANCE->value)
        ->where('status', InvoiceStatus::DRAFT->value)
        ->whereHas('lineItems', fn ($q) => $q->where('line_type', InvoiceLineType::ADJUST_EXTRA_SESSION->value))
        ->first();
    expect($settlement)->not->toBeNull();
});

test('the command dry-run creates no reconciliation rows', function () {
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
    BillingSchedule::factory()->forSchool($school)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
    ]);

    $this->artisan('billing:reconcile-advance --dry-run')->assertSuccessful();

    expect(AdvanceReconciliation::count())->toBe(0);
});
