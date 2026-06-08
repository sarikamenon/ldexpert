<?php

declare(strict_types=1);

use App\Enums\BillingMode;
use App\Enums\SessionLogStatus;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\TherapistBill;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function billableSchoolLog(School $school, User $therapist, User $student, string $date, float $amount = 100.0): SessionLog
{
    return SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => $amount,
        'invoice_id' => null,
        'session_date' => $date,
    ]);
}

test('billing generate command processes all due schedules', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    BillingSchedule::factory()->forSchool($school)->due()->create();

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

    $this->artisan('billing:generate')
        ->assertExitCode(0)
        ->expectsOutputToContain('Done:');
});

test('billing generate command with type filter', function () {
    User::factory()->admin()->create();

    $this->artisan('billing:generate', ['--type' => 'school_invoice'])
        ->assertExitCode(0)
        ->expectsOutputToContain('No due schedules found.');
});

test('billing generate command with schedule id', function () {
    User::factory()->admin()->create();
    $school = School::factory()->create();
    $schedule = BillingSchedule::factory()->forSchool($school)->create();

    $this->artisan('billing:generate', ['--schedule' => $schedule->id])
        ->assertExitCode(0);
});

test('billing generate command with invalid schedule id returns failure', function () {
    $this->artisan('billing:generate', ['--schedule' => 99999])
        ->assertExitCode(1)
        ->expectsOutputToContain('not found');
});

test('billing generate dry run does not create invoices', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    BillingSchedule::factory()->forSchool($school)->due()->create();

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

    $this->artisan('billing:generate', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN');

    $this->assertDatabaseMissing('invoices', [
        'school_id' => $school->id,
    ]);
});

test('billing generate shows message when no due schedules', function () {
    User::factory()->admin()->create();

    $this->artisan('billing:generate')
        ->assertExitCode(0)
        ->expectsOutputToContain('No due schedules found.');
});

test('standard generation sweeps a late prior-period session onto the current invoice', function () {
    Carbon::setTestNow('2026-05-20 02:00:00');

    User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => false]);
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    // Current period (semi-monthly, May 16–31, anchored off last_period_end = May 15).
    $schedule = BillingSchedule::factory()->forSchool($school)->due()->create([
        'billing_mode' => BillingMode::STANDARD->value,
        'last_period_end' => '2026-05-15',
    ]);

    // One session in the current period, one late session from the PRIOR period
    // (before May 16) that was never invoiced — the sweep must pick up both.
    billableSchoolLog($school, $therapist, $student, '2026-05-18', amount: 100.0);
    $late = billableSchoolLog($school, $therapist, $student, '2026-05-10', amount: 60.0);

    $this->artisan('billing:generate', ['--schedule' => $schedule->id])->assertExitCode(0);

    $invoice = Invoice::where('school_id', $school->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->billing_period_start->toDateString())->toBe('2026-05-16')
        ->and($invoice->billing_period_end->toDateString())->toBe('2026-05-31')
        // Both sessions invoiced — the late prior-period one is swept in.
        ->and((float) $invoice->total)->toBe(160.0)
        ->and($late->fresh()->invoice_id)->toBe($invoice->id);

    Carbon::setTestNow();
});

test('standard school invoice due date honors the schedule payment terms, not a hardcoded 30', function () {
    Carbon::setTestNow('2026-05-20 02:00:00');

    User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => false]);
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    $schedule = BillingSchedule::factory()->forSchool($school)->due()->create([
        'billing_mode' => BillingMode::STANDARD->value,
        'last_period_end' => '2026-05-15',
        'payment_terms_days' => 45,
    ]);

    billableSchoolLog($school, $therapist, $student, '2026-05-18', amount: 100.0);

    $this->artisan('billing:generate', ['--schedule' => $schedule->id])->assertExitCode(0);

    $invoice = Invoice::where('school_id', $school->id)->first();
    expect($invoice)->not->toBeNull()
        // due_date = invoice_date + 45 (the schedule's terms), not + 30.
        ->and($invoice->due_date->toDateString())->toBe($invoice->invoice_date->copy()->addDays(45)->toDateString());

    Carbon::setTestNow();
});

test('weekly generation produces a 7-day period; bi-weekly produces a 14-day period', function () {
    Carbon::setTestNow('2026-05-20 02:00:00');

    User::factory()->admin()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    $weeklySchool = School::factory()->create(['is_private_student' => false]);
    $weekly = BillingSchedule::factory()->forSchool($weeklySchool)->weekly()->due()->create([
        'billing_mode' => BillingMode::STANDARD->value,
        'last_period_end' => null,
    ]);
    billableSchoolLog($weeklySchool, $therapist, $student, '2026-05-19', amount: 100.0);

    $biWeeklySchool = School::factory()->create(['is_private_student' => false]);
    $biWeekly = BillingSchedule::factory()->forSchool($biWeeklySchool)->biWeekly()->due()->create([
        'billing_mode' => BillingMode::STANDARD->value,
        'last_period_end' => null,
    ]);
    billableSchoolLog($biWeeklySchool, $therapist, $student, '2026-05-19', amount: 100.0);

    $this->artisan('billing:generate', ['--schedule' => $weekly->id])->assertExitCode(0);
    $this->artisan('billing:generate', ['--schedule' => $biWeekly->id])->assertExitCode(0);

    $weeklyInvoice = Invoice::where('school_id', $weeklySchool->id)->first();
    $biWeeklyInvoice = Invoice::where('school_id', $biWeeklySchool->id)->first();

    // Period length is the frequency's signature: weekly spans 7 days (Mon–Sun
    // inclusive), bi-weekly spans 14 days.
    expect($weeklyInvoice)->not->toBeNull()
        ->and((int) $weeklyInvoice->billing_period_start->diffInDays($weeklyInvoice->billing_period_end) + 1)->toBe(7);

    expect($biWeeklyInvoice)->not->toBeNull()
        ->and((int) $biWeeklyInvoice->billing_period_start->diffInDays($biWeeklyInvoice->billing_period_end) + 1)->toBe(14);

    Carbon::setTestNow();
});

test('therapist-bill generation stamps sessions, sets total and due date', function () {
    Carbon::setTestNow('2026-05-20 02:00:00');

    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');

    User::factory()->admin()->create();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    $therapist = User::factory()->therapist()->create();
    $therapist->therapistProfile()->create(
        TherapistProfile::factory()->make(['user_id' => $therapist->id])->toArray()
    );

    $schedule = BillingSchedule::factory()->forTherapist($therapist)->due()->create([
        'last_period_end' => '2026-05-15',
        'payment_terms_days' => 30,
    ]);

    $log = SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_therapist' => true,
        'therapist_billable_amount' => 80.0,
        'therapist_bill_id' => null,
        'session_date' => '2026-05-18',
    ]);

    $this->artisan('billing:generate', ['--schedule' => $schedule->id])->assertExitCode(0);

    $bill = TherapistBill::where('therapist_id', $therapist->id)->first();
    expect($bill)->not->toBeNull()
        ->and((float) $bill->total_due)->toBe(80.0)
        ->and($bill->due_date->toDateString())->toBe(now()->addDays(30)->toDateString())
        // Session stamped onto the bill.
        ->and($log->fresh()->therapist_bill_id)->toBe($bill->id);

    Carbon::setTestNow();
});
