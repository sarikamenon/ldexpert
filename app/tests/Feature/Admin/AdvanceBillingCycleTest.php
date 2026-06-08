<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingAutomationService;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * End-to-end advance billing cycle through BillingAutomationService:
 *   Run A — first run: advance-charges the upcoming period from scheduled sessions.
 *   Run B — next run: reconciles Run A's charges against actual session outcomes
 *           (the adjust_* branches) AND advance-charges the following period.
 *
 * This exercises the per-run reconciliation inside processAdvanceSchedule, distinct
 * from the 10th-of-month catch-up command (AdvanceReconciliationTest).
 */
beforeEach(function () {
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');

    User::factory()->admin()->create(); // system user for snapshots

    // Deterministic school advance rate ($60) so charge lines are predictable.
    $this->mock(SessionLogRateService::class, function ($mock) {
        $mock->shouldReceive('calculateDualBilling')->andReturn([
            'school' => ['invoice_amount' => 60.0],
            'therapist' => ['billable_amount' => 80.0],
        ]);
    });

    $this->service = app(BillingAutomationService::class);
});

/**
 * @return array{0: School, 1: BillingSchedule}
 */
function advanceCycleSchool(): array
{
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);

    // billing_start_date anchors April → first run upcoming period = May.
    $schedule = BillingSchedule::factory()->forSchool($school)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'frequency' => \App\Enums\BillingFrequency::MONTHLY->value,
        'payment_terms_days' => 30,
        'billing_start_date' => '2026-04-15',
        'last_period_end' => null,
        'last_run_at' => null,
    ]);

    return [$school, $schedule];
}

function maySchedule(School $school, string $date): Schedule
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $service = Service::factory()->create();

    return Schedule::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'service_id' => $service->id,
        'schedule_date' => $date,
        'status' => 'scheduled',
        'invoice_id' => null,
    ]);
}

test('first advance run charges the upcoming period and stamps the schedules', function () {
    [$school, $config] = advanceCycleSchool();
    $s1 = maySchedule($school, '2026-05-05');
    $s2 = maySchedule($school, '2026-05-12');

    $result = $this->service->processSingleSchedule($config->fresh());

    expect($result->status)->toBe(\App\Enums\BillingScheduleRunStatus::SUCCESS->value)
        ->and($result->billingPeriodStart)->toBe('2026-05-01')
        ->and((float) $result->totalAmount)->toBe(120.0); // 2 × $60

    $invoice = Invoice::find($result->invoiceId);
    expect($invoice->billing_mode)->toBe(BillingMode::ADVANCE)
        ->and($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->lineItems()->where('line_type', InvoiceLineType::ADVANCE_SCHEDULED->value)->count())->toBe(2);

    // Schedules stamped so they are never re-charged.
    expect($s1->fresh()->invoice_id)->toBe($invoice->id)
        ->and($s2->fresh()->invoice_id)->toBe($invoice->id);
});

test('second advance run reconciles the prior period against actual outcomes and charges the next period', function () {
    [$school, $config] = advanceCycleSchool();

    // May schedules advance-charged on Run A.
    $sMatched = maySchedule($school, '2026-05-05');     // stays administered → no adjustment
    $sNonBillable = maySchedule($school, '2026-05-12'); // becomes non-billable → full credit
    $sNoShow = maySchedule($school, '2026-05-19');      // no-show → partial credit
    $sNoSession = maySchedule($school, '2026-05-26');   // no session logged → did-not-occur credit

    // Run A — creates the May advance invoice (4 × $60 = $240) and advances the schedule.
    $runA = $this->service->processSingleSchedule($config->fresh());
    expect((float) $runA->totalAmount)->toBe(240.0);

    // Record the actual May outcomes (session logs keyed to the charged schedules).
    approvedLogFor($school, $sMatched, SessionOutcome::SERVICES_ADMINISTERED, billable: true, amount: 60.0);
    approvedLogFor($school, $sNonBillable, SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT, billable: false, amount: 0.0);
    approvedLogFor($school, $sNoShow, SessionOutcome::NO_SHOW, billable: true, amount: 30.0);
    // sNoSession: intentionally no session log.

    // A June schedule for Run A's upcoming-period advance charge.
    $sJune = maySchedule($school, '2026-06-09');

    // Run B — reconciles May + advance-charges June.
    $runB = $this->service->processSingleSchedule($config->fresh());

    $invoiceB = Invoice::find($runB->invoiceId);
    $adjustments = $invoiceB->lineItems()
        ->whereIn('line_type', [
            InvoiceLineType::ADJUST_NO_SHOW->value,
            InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value,
            InvoiceLineType::ADJUST_RATE_DIFFERENCE->value,
        ])->get();

    // Adjustments: non-billable -60, no-show (30-60) -30, did-not-occur -60 = -150.
    expect($runB->adjustmentsCount)->toBe(3)
        ->and((float) $adjustments->sum('total'))->toBe(-150.0);

    // June advance charge = 1 × $60. Net = 60 - 150 → clamped 0 with carry-forward 90.
    $advanceLines = $invoiceB->lineItems()->where('line_type', InvoiceLineType::ADVANCE_SCHEDULED->value)->get();
    expect((float) $advanceLines->sum('total'))->toBe(60.0)
        ->and((float) $invoiceB->total)->toBe(0.0)
        ->and((float) $invoiceB->carry_forward_balance)->toBe(90.0);
});

test('an advance run auto-sends the generated invoice when the schedule opts in', function () {
    \Illuminate\Support\Facades\Mail::fake();

    [$school, $config] = advanceCycleSchool();
    $config->update(['auto_send' => true]);
    maySchedule($school, '2026-05-05'); // one $60 advance charge → non-zero invoice

    $result = $this->service->processSingleSchedule($config->fresh());

    $invoice = Invoice::find($result->invoiceId);

    expect($result->autoSent)->toBeTrue()
        ->and($invoice->status)->toBe(InvoiceStatus::SENT);

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\InvoiceMail::class);
});

function approvedLogFor(School $school, Schedule $schedule, SessionOutcome $outcome, bool $billable, float $amount): SessionLog
{
    return SessionLog::factory()->create([
        'school_id' => $school->id,
        'schedule_id' => $schedule->id,
        'therapist_id' => $schedule->therapist_id,
        'student_id' => $schedule->student_id,
        'service_id' => $schedule->service_id,
        'status' => SessionLogStatus::APPROVED->value,
        'outcome' => $outcome->value,
        'is_billable_school' => $billable,
        'school_invoice_amount' => $amount,
        'session_date' => $schedule->schedule_date,
    ]);
}
