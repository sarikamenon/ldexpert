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

// The semi-monthly half of May that contains a given day, matching how the
// 1st-of-month advance run stamps billing_period on each ADVANCE_SCHEDULED line.
// @return array{string, string}
function mayHalfFor(int $day): array
{
    return $day <= 15 ? ['2026-05-01', '2026-05-15'] : ['2026-05-16', '2026-05-31'];
}

// A prior-month (May) advance invoice carrying one ADVANCE_SCHEDULED line for
// $amount, stamped with the semi-monthly half the session falls in (default May 12).
function priorAdvanceInvoiceWithLine(School $school, int $scheduleId, ?int $sessionLogId, float $amount, int $day = 12): Invoice
{
    [$start, $end] = mayHalfFor($day);

    $invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'billing_mode' => BillingMode::ADVANCE->value,
        'status' => InvoiceStatus::SENT->value,
        'billing_period_start' => $start,
        'billing_period_end' => $end,
    ]);

    $invoice->lineItems()->create([
        'line_type' => InvoiceLineType::ADVANCE_SCHEDULED->value,
        'description' => 'May advance charge',
        'billing_period_start' => $start,
        'billing_period_end' => $end,
        'quantity' => 1,
        'unit_price' => $amount,
        'total' => $amount,
        'sort_order' => 0,
        'schedule_id' => $scheduleId,
        'session_log_id' => $sessionLogId,
    ]);

    return $invoice;
}

// A semi-monthly schedule reconciles BOTH halves of the prior month, so
// reconcileSchedule returns one result per half. Pick the half that produced
// lines or a document (the one the test set up); fall back to the first.
// @param  list<array<string, mixed>>  $results
// @return array<string, mixed>
function materialResult(array $results): array
{
    foreach ($results as $r) {
        if ($r['lines'] > 0 || $r['settlement_invoice_id'] !== null || $r['credit_note_ledger_entry_id'] !== null) {
            return $r;
        }
    }

    return $results[0];
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

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(-100.0)
        ->and($result['credit_note_ledger_entry_id'])->not->toBeNull()
        ->and($result['settlement_invoice_id'])->toBeNull();

    $entry = LedgerEntry::find($result['credit_note_ledger_entry_id']);
    expect($entry->transaction_type)->toBe(TransactionType::CREDIT_NOTE)
        ->and((float) $entry->amount)->toBe(100.0)
        // Posted against the school account (we owe the family).
        ->and($entry->ledgerable_type)->toBe(School::class)
        ->and((int) $entry->ledgerable_id)->toBe($school->id)
        // CREDIT_NOTE balanceDelta is -1: a fresh account drops to -100.
        ->and((float) $entry->balance_after)->toBe(-100.0)
        // recorded_at = the run date (the 10th), NOT the May session date (Q9: not backdated).
        ->and($entry->recorded_at->toDateString())->toBe('2026-06-10')
        // Posted by the resolved system admin, and notes reference the period + schedule.
        ->and($entry->recorded_by_id)->toBe(User::where('role', 'admin')->first()->id)
        ->and($entry->notes)->toContain('May 2026')
        ->and($entry->notes)->toContain('schedule #'.$config->id);
});

test('a late-approved extra billable May session produces a draft settlement invoice', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-20']);

    // No prior billing for this session (already_billed = 0); approved billable $100.
    mayApprovedLog($school, $schedule->id, billable: true, amount: 100.0);

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(100.0)
        ->and($result['settlement_invoice_id'])->not->toBeNull();

    $settlement = Invoice::find($result['settlement_invoice_id']);
    expect($settlement->status)->toBe(InvoiceStatus::DRAFT)
        ->and($settlement->billing_mode)->toBe(BillingMode::ADVANCE)
        ->and((float) $settlement->total)->toBe(100.0)
        ->and($settlement->lineItems()->count())->toBe(1);
});

test('mixed charges and credits netting positive produce ONE settlement invoice, no credit note', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);

    $extra = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-10']);
    $cancelled = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-20']);

    // Extra billable session never billed → +120. Advance-charged $100 then non-billable → -100. Net +20.
    mayApprovedLog($school, $extra->id, billable: true, amount: 120.0);
    priorAdvanceInvoiceWithLine($school, $cancelled->id, null, 100.0);
    mayApprovedLog($school, $cancelled->id, billable: false, amount: 0.0);

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(20.0)
        ->and($result['settlement_invoice_id'])->not->toBeNull()
        ->and($result['credit_note_ledger_entry_id'])->toBeNull();

    $invoice = Invoice::find($result['settlement_invoice_id']);
    // Carries BOTH lines (the extra charge and the cancellation credit), netting to 20.
    expect((float) $invoice->total)->toBe(20.0)
        ->and($invoice->lineItems()->count())->toBe(2)
        ->and((float) $invoice->lineItems()->sum('total'))->toBe(20.0)
        ->and($invoice->lineItems()->where('line_type', InvoiceLineType::ADJUST_EXTRA_SESSION->value)->exists())->toBeTrue()
        ->and($invoice->lineItems()->where('line_type', InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value)->exists())->toBeTrue()
        // No separate credit note created.
        ->and(LedgerEntry::where('transaction_type', TransactionType::CREDIT_NOTE->value)->count())->toBe(0);
});

test('mixed charges and credits netting negative produce ONE credit note, no settlement invoice', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);

    $extra = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-10']);
    $cancelled = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-20']);

    // Extra +50, cancelled -100 → net -50 → single credit note, no invoice.
    mayApprovedLog($school, $extra->id, billable: true, amount: 50.0);
    priorAdvanceInvoiceWithLine($school, $cancelled->id, null, 100.0);
    mayApprovedLog($school, $cancelled->id, billable: false, amount: 0.0);

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(-50.0)
        ->and($result['settlement_invoice_id'])->toBeNull()
        ->and($result['credit_note_ledger_entry_id'])->not->toBeNull();

    $entry = LedgerEntry::find($result['credit_note_ledger_entry_id']);
    expect((float) $entry->amount)->toBe(50.0);
});

test('mixed charges and credits netting exactly zero produce neither document', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);

    $extra = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-10']);
    $cancelled = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-20']);

    // Extra +100, cancelled -100 → net 0 → only the reconciliation row.
    mayApprovedLog($school, $extra->id, billable: true, amount: 100.0);
    priorAdvanceInvoiceWithLine($school, $cancelled->id, null, 100.0);
    mayApprovedLog($school, $cancelled->id, billable: false, amount: 0.0);

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    expect($result['status'])->toBe('reconciled')
        ->and($result['net_amount'])->toBe(0.0)
        ->and($result['settlement_invoice_id'])->toBeNull()
        ->and($result['credit_note_ledger_entry_id'])->toBeNull()
        // Semi-monthly: both halves of May are marked reconciled.
        ->and(AdvanceReconciliation::count())->toBe(2);
});

test('a reconciliation credit note chains its balance_after onto the school ledger', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    // Seed a prior credit balance on the school account: -40 before reconciliation.
    $ledger = app(\App\Domain\Finance\Services\LedgerService::class);
    $seed = $ledger->createCreditNoteForSchool($school->id, 40.0, 'Seed credit', User::where('role', 'admin')->first()->id, now());
    expect((float) $seed->balance_after)->toBe(-40.0);

    // Over-charged $100 in advance, session later non-billable → -100 reconciliation credit.
    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: false, amount: 0.0);

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    $entry = LedgerEntry::find($result['credit_note_ledger_entry_id']);

    // The new credit chains onto the seeded balance: -40 then -140.
    expect((float) $entry->amount)->toBe(100.0)
        ->and((float) $entry->balance_after)->toBe(-140.0);
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

    $result = materialResult($this->service->reconcileSchedule($config, now()));

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

    // Every half of the second run is skipped (already reconciled).
    expect(collect($second)->every(fn (array $r): bool => $r['status'] === 'skipped_already_reconciled'))->toBeTrue()
        ->and(LedgerEntry::where('transaction_type', TransactionType::CREDIT_NOTE->value)->count())->toBe(1)
        // Semi-monthly: both halves of May, written once across the two runs.
        ->and(AdvanceReconciliation::count())->toBe(2);
});

test('a zero-delta period still writes a reconciliation row to mark it done', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    // Charged $100, approved billable $100 → delta 0.
    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: true, amount: 100.0);

    $result = materialResult($this->service->reconcileSchedule($config, now()));

    expect($result['status'])->toBe('reconciled')
        ->and($result['lines'])->toBe(0)
        // Semi-monthly: both halves of May are marked reconciled.
        ->and(AdvanceReconciliation::count())->toBe(2);
});

test('dry run computes the delta without creating anything', function () {
    $school = advanceSchoolForRecon();
    $config = advanceScheduleFor($school);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'schedule_date' => '2026-05-12']);

    priorAdvanceInvoiceWithLine($school, $schedule->id, null, 100.0);
    mayApprovedLog($school, $schedule->id, billable: false, amount: 0.0);

    // Baseline: the testing DB may carry demo ledger rows from seed migrations.
    // A dry run must not add any NEW entries on top of whatever already exists.
    $ledgerBefore = LedgerEntry::count();

    $result = materialResult($this->service->reconcileSchedule($config, now(), dryRun: true));

    expect($result['status'])->toBe('dry_run')
        ->and($result['net_amount'])->toBe(-100.0)
        ->and(AdvanceReconciliation::count())->toBe(0)
        ->and(LedgerEntry::count())->toBe($ledgerBefore);
});
