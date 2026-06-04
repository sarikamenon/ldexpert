<?php

declare(strict_types=1);

use App\Domain\Billing\Services\AdvanceReconciliationService;
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

    User::factory()->admin()->create(); // system user for ledger + snapshots

    Carbon::setTestNow('2026-06-10 02:00:00'); // the 10th-of-month run

    $this->service = app(AdvanceReconciliationService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function advanceSchoolForRecon(): School
{
    return School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
}

function advanceScheduleFor(School $school): BillingSchedule
{
    return BillingSchedule::factory()->forSchool($school)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'payment_terms_days' => 30,
    ]);
}

// A prior-month (May) advance invoice carrying one ADVANCE_SCHEDULED line for $amount.
function priorAdvanceInvoiceWithLine(School $school, int $scheduleId, ?int $sessionLogId, float $amount): Invoice
{
    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'billing_mode' => BillingMode::ADVANCE->value,
        'status' => InvoiceStatus::SENT->value,
        'billing_period_start' => '2026-05-01',
        'billing_period_end' => '2026-05-31',
    ]);

    $invoice->lineItems()->create([
        'line_type' => InvoiceLineType::ADVANCE_SCHEDULED->value,
        'description' => 'May advance charge',
        'billing_period_start' => '2026-05-01',
        'billing_period_end' => '2026-05-31',
        'quantity' => 1,
        'unit_price' => $amount,
        'total' => $amount,
        'sort_order' => 0,
        'schedule_id' => $scheduleId,
        'session_log_id' => $sessionLogId,
    ]);

    return $invoice;
}

function mayApprovedLog(School $school, int $scheduleId, bool $billable, float $amount): \App\Models\SessionLog
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    return \App\Models\SessionLog::factory()->create([
        'school_id' => $school->id,
        'schedule_id' => $scheduleId,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => $billable,
        'school_invoice_amount' => $amount,
        'outcome' => $billable ? SessionOutcome::SERVICES_ADMINISTERED->value : SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT->value,
        'session_date' => '2026-05-12',
    ]);
}

test('a late-approved non-billable May session produces a reconciliation credit note', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    // Charged $100 in advance for May; the session was later approved as non-billable.
    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: false, amount: 0.0);

    $result = $this->service->reconcileSchedule($config, now());

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(-100.0)
        ->and($result['credit_note_ledger_entry_id'])->not->toBeNull()
        ->and($result['settlement_invoice_id'])->toBeNull();

    $entry = LedgerEntry::find($result['credit_note_ledger_entry_id']);
    expect($entry->transaction_type)->toBe(TransactionType::CREDIT_NOTE)
        ->and((float) $entry->amount)->toBe(100.0);
});

test('a late-approved extra billable May session produces a draft settlement invoice', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-20']);

    // No prior billing for this session (already_billed = 0); approved billable $100.
    mayApprovedLog($school, $schedule->id, billable: true, amount: 100.0);

    $result = $this->service->reconcileSchedule($config, now());

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(100.0)
        ->and($result['settlement_invoice_id'])->not->toBeNull();

    $settlement = Invoice::find($result['settlement_invoice_id']);
    expect($settlement->status)->toBe(InvoiceStatus::DRAFT)
        ->and($settlement->billing_mode)->toBe(BillingMode::ADVANCE)
        ->and((float) $settlement->total)->toBe(100.0)
        ->and($settlement->lineItems()->count())->toBe(1);
});

test('a current-month June session is NOT touched by the June 10 run', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-06-05']);

    // June (current month) approved billable session — must be excluded.
    \App\Models\SessionLog::factory()->create([
        'school_id' => $school->id,
        'schedule_id' => $schedule->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 100.0,
        'session_date' => '2026-06-05',
    ]);

    $result = $this->service->reconcileSchedule($config, now());

    expect($result['status'])->toBe('reconciled')
        ->and($result['lines'])->toBe(0)
        ->and($result['settlement_invoice_id'])->toBeNull()
        ->and($result['credit_note_ledger_entry_id'])->toBeNull();
});

test('running reconcile twice for the same period does not double-credit', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: false, amount: 0.0);

    $this->service->reconcileSchedule($config, now());
    $second = $this->service->reconcileSchedule($config, now());

    expect($second['status'])->toBe('skipped_already_reconciled')
        ->and(LedgerEntry::where('transaction_type', TransactionType::CREDIT_NOTE->value)->count())->toBe(1)
        ->and(AdvanceReconciliation::count())->toBe(1);
});

test('a zero-delta period still writes a reconciliation row to mark it done', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    // Charged $100, approved billable $100 → delta 0.
    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: true, amount: 100.0);

    $result = $this->service->reconcileSchedule($config, now());

    expect($result['status'])->toBe('reconciled')
        ->and($result['lines'])->toBe(0)
        ->and(AdvanceReconciliation::count())->toBe(1);
});

test('dry run computes the delta without creating anything', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: false, amount: 0.0);

    $result = $this->service->reconcileSchedule($config, now(), dryRun: true);

    expect($result['status'])->toBe('dry_run')
        ->and($result['net_amount'])->toBe(-100.0)
        ->and(AdvanceReconciliation::count())->toBe(0)
        ->and(LedgerEntry::count())->toBe(0);
});
