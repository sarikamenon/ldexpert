<?php

declare(strict_types=1);

use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Enums\TransactionType;
use App\Models\AdvanceReconciliation;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\LedgerEntry;
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

    // The advance school only — semi-monthly, so both halves of May are marked.
    expect(AdvanceReconciliation::count())->toBe(2);
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

test('the no-arg command reconciles every active advance schedule in one run', function () {
    // School A nets a charge (late extra billable session) → settlement invoice.
    $schoolA = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
    $configA = BillingSchedule::factory()->forSchool($schoolA)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'payment_terms_days' => 30,
    ]);
    $extra = Schedule::factory()->create(['school_id' => $schoolA->id, 'schedule_date' => '2026-05-12']);
    commandReconLog($schoolA, $extra->id, billable: true, amount: 100.0);

    // School B nets a credit (advance-charged then non-billable) → credit note.
    $schoolB = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
    $configB = BillingSchedule::factory()->forSchool($schoolB)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'payment_terms_days' => 30,
    ]);
    $cancelled = Schedule::factory()->create(['school_id' => $schoolB->id, 'schedule_date' => '2026-05-20']);
    commandPriorAdvanceLine($schoolB, $cancelled->id, 80.0);
    commandReconLog($schoolB, $cancelled->id, billable: false, amount: 0.0);

    $this->artisan('billing:reconcile-advance')->assertSuccessful();

    // Both schedules reconciled in the single run; semi-monthly → 2 halves each.
    expect(AdvanceReconciliation::count())->toBe(4)
        ->and(AdvanceReconciliation::where('billing_schedule_id', $configA->id)->exists())->toBeTrue()
        ->and(AdvanceReconciliation::where('billing_schedule_id', $configB->id)->exists())->toBeTrue();

    // School A → a draft settlement invoice for +100; School B → none.
    expect(Invoice::where('school_id', $schoolA->id)->where('status', InvoiceStatus::DRAFT->value)->count())->toBe(1)
        ->and(Invoice::where('school_id', $schoolB->id)->where('status', InvoiceStatus::DRAFT->value)->count())->toBe(0);

    // School B → a single credit note for 80; School A → none.
    expect(LedgerEntry::where('transaction_type', TransactionType::CREDIT_NOTE->value)->where('ledgerable_id', $schoolB->id)->count())->toBe(1)
        ->and(LedgerEntry::where('transaction_type', TransactionType::CREDIT_NOTE->value)->where('ledgerable_id', $schoolA->id)->count())->toBe(0);
});

function commandReconLog(School $school, int $scheduleId, bool $billable, float $amount): SessionLog
{
    return SessionLog::factory()->create([
        'school_id' => $school->id,
        'schedule_id' => $scheduleId,
        'therapist_id' => User::factory()->therapist()->create()->id,
        'student_id' => User::factory()->student()->create()->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => $billable,
        'school_invoice_amount' => $amount,
        'outcome' => $billable ? SessionOutcome::SERVICES_ADMINISTERED->value : SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT->value,
        'session_date' => '2026-05-12',
    ]);
}

function commandPriorAdvanceLine(School $school, int $scheduleId, float $amount): Invoice
{
    // The session is logged on May 12, so the original advance run stamps the
    // first semi-monthly half (1st–15th) — match it so already_billed lines up.
    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'billing_mode' => BillingMode::ADVANCE->value,
        'status' => InvoiceStatus::SENT->value,
        'billing_period_start' => '2026-05-01',
        'billing_period_end' => '2026-05-15',
    ]);

    $invoice->lineItems()->create([
        'line_type' => InvoiceLineType::ADVANCE_SCHEDULED->value,
        'description' => 'May advance charge',
        'billing_period_start' => '2026-05-01',
        'billing_period_end' => '2026-05-15',
        'quantity' => 1,
        'unit_price' => $amount,
        'total' => $amount,
        'sort_order' => 0,
        'schedule_id' => $scheduleId,
        'session_log_id' => null,
    ]);

    return $invoice;
}
