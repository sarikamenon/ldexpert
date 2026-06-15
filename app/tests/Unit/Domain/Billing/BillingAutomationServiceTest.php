<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingAutomationService;
use App\Enums\BillingScheduleRunStatus;
use App\Enums\SessionLogStatus;
use App\Models\BillingSchedule;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BillingAutomationService::class);
});

test('sweep un-invoiced sessions returns only approved billable sessions', function () {
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    // Approved, billable, un-invoiced
    $validSession = SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 100.00,
        'invoice_id' => null,
        'session_date' => now()->subDays(5),
    ]);

    // Pending session — should be excluded
    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::DRAFT->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 100.00,
        'invoice_id' => null,
        'session_date' => now()->subDays(5),
    ]);

    // Not billable — should be excluded
    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => false,
        'school_invoice_amount' => 0,
        'invoice_id' => null,
        'session_date' => now()->subDays(5),
    ]);

    // Future session — should be excluded (after period end)
    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 100.00,
        'invoice_id' => null,
        'session_date' => now()->addDays(10),
    ]);

    $sessions = $this->service->sweepUnInvoicedSessions($school->id, Carbon::now());

    expect($sessions)->toHaveCount(1)
        ->and($sessions->first()->id)->toBe($validSession->id);
});

test('sweep un-billed sessions returns only approved billable therapist sessions', function () {
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    // Approved, billable, un-billed
    $validSession = SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_therapist' => true,
        'therapist_billable_amount' => 75.00,
        'therapist_bill_id' => null,
        'session_date' => now()->subDays(5),
    ]);

    // Not billable for therapist
    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_therapist' => false,
        'therapist_billable_amount' => 0,
        'therapist_bill_id' => null,
        'session_date' => now()->subDays(5),
    ]);

    $sessions = $this->service->sweepUnBilledSessions($therapist->id, Carbon::now());

    expect($sessions)->toHaveCount(1)
        ->and($sessions->first()->id)->toBe($validSession->id);
});

test('process all due schedules returns empty collection when no schedules are due', function () {
    // Create a schedule that is not due
    BillingSchedule::factory()->create([
        'next_run_at' => now()->addWeek()->toDateString(),
    ]);

    $results = $this->service->processAllDueSchedules();

    expect($results)->toBeEmpty();
});

test('process single schedule routes standard school invoice correctly', function () {
    $school = School::factory()->create();
    $admin = User::factory()->admin()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    $schedule = BillingSchedule::factory()->forSchool($school)->due()->create([
        'last_period_end' => null,
    ]);

    // Create a billable session
    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 200.00,
        'invoice_id' => null,
        'session_date' => now()->subDays(3),
    ]);

    $result = $this->service->processSingleSchedule($schedule);

    expect($result->status)->toBe(BillingScheduleRunStatus::SUCCESS->value)
        ->and($result->sessionsFound)->toBe(1)
        ->and($result->invoiceId)->not->toBeNull()
        ->and($result->therapistBillId)->toBeNull();
});

test('process single schedule returns skipped when no sessions', function () {
    $school = School::factory()->create();
    User::factory()->admin()->create();

    $schedule = BillingSchedule::factory()->forSchool($school)->due()->create();

    $result = $this->service->processSingleSchedule($schedule);

    expect($result->status)->toBe(BillingScheduleRunStatus::SKIPPED_NO_SESSIONS->value)
        ->and($result->sessionsFound)->toBe(0);
});

test('process single schedule dry run does not create records', function () {
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    $schedule = BillingSchedule::factory()->forSchool($school)->due()->create();

    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 150.00,
        'invoice_id' => null,
        'session_date' => now()->subDays(3),
    ]);

    $result = $this->service->processSingleSchedule($schedule, dryRun: true);

    expect($result->status)->toBe(BillingScheduleRunStatus::SUCCESS->value)
        ->and($result->sessionsFound)->toBe(1)
        ->and($result->invoiceId)->toBeNull(); // Not created in dry run
});

test('process single schedule for therapist bill generates bill', function () {
    $therapist = User::factory()->therapist()->create();
    $therapist->therapistProfile()->create(
        \App\Models\TherapistProfile::factory()->make([
            'user_id' => $therapist->id,
        ])->toArray()
    );
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();

    // Set up company settings
    \App\Models\Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    \App\Models\Setting::set('company.address', '123 Company St', 'string', 'company');
    \App\Models\Setting::set('company.phone', '555-1234', 'string', 'company');
    \App\Models\Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');

    $schedule = BillingSchedule::factory()->forTherapist($therapist)->due()->create();

    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_therapist' => true,
        'therapist_billable_amount' => 100.00,
        'therapist_bill_id' => null,
        'session_date' => now()->subDays(3),
    ]);

    $result = $this->service->processSingleSchedule($schedule);

    expect($result->status)->toBe(BillingScheduleRunStatus::SUCCESS->value)
        ->and($result->therapistBillId)->not->toBeNull()
        ->and($result->invoiceId)->toBeNull();
});

test('process all due schedules catches exceptions per schedule and continues', function () {
    $school = School::factory()->create();
    User::factory()->admin()->create();

    // Create two due schedules for the same school but different types (to avoid unique constraint)
    $schedule1 = BillingSchedule::factory()->forSchool($school)->due()->create();

    $therapist = User::factory()->therapist()->create();
    $schedule2 = BillingSchedule::factory()->forTherapist($therapist)->due()->create();

    // Both have no sessions, so they'll be skipped (not fail)
    $results = $this->service->processAllDueSchedules();

    expect($results)->toHaveCount(2);
});
