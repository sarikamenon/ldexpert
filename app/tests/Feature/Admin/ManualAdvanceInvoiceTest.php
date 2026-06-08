<?php

declare(strict_types=1);

use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateInvoiceDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\InvoiceLineType;
use App\Models\BillingSchedule;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');

    // Fixed rate so the advance charge lines are deterministic.
    $this->mock(SessionLogRateService::class, function ($mock) {
        $mock->shouldReceive('calculateDualBilling')->andReturn([
            'school' => ['invoice_amount' => 100.0],
            'therapist' => ['billable_amount' => 50.0],
        ]);
    });

    $this->service = app(InvoiceService::class);
});

function advanceSchool(): School
{
    // Private-student → advance billing mode; pin a 2-char state for the snapshot.
    return School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
}

function scheduleInPeriod(School $school): Schedule
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    return Schedule::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_date' => '2026-06-10',
        'invoice_id' => null,
    ]);
}

test('manual invoice for an advance school builds ADVANCE_SCHEDULED charge lines and sets advance mode', function () {
    $school = advanceSchool();
    $schedule = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$schedule->id],
    ]);

    $invoice = $this->service->generateInvoice($admin, $dto);

    expect($invoice->billing_mode)->toBe(BillingMode::ADVANCE)
        ->and((float) $invoice->total)->toBe(100.0)
        ->and($invoice->lineItems)->toHaveCount(1)
        ->and($invoice->lineItems->first()->line_type)->toBe(InvoiceLineType::ADVANCE_SCHEDULED)
        ->and($invoice->lineItems->first()->schedule_id)->toBe($schedule->id);
});

test('manual advance invoice stamps schedules.invoice_id on the billed schedules', function () {
    $school = advanceSchool();
    $schedule = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$schedule->id],
    ]);

    $invoice = $this->service->generateInvoice($admin, $dto);

    $schedule->refresh();
    expect($schedule->invoice_id)->toBe($invoice->id);
});

test('manual advance invoice only includes the admin-selected schedules', function () {
    $school = advanceSchool();
    $selected = scheduleInPeriod($school);
    $other = scheduleInPeriod($school); // in period but not selected
    $admin = User::factory()->admin()->create();

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$selected->id],
    ]);

    $invoice = $this->service->generateInvoice($admin, $dto);

    expect($invoice->lineItems)->toHaveCount(1)
        ->and($invoice->lineItems->first()->schedule_id)->toBe($selected->id);

    $other->refresh();
    expect($other->invoice_id)->toBeNull();
});

test('manual advance invoice produces only charge lines, no adjustment lines', function () {
    $school = advanceSchool();
    $schedule = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$schedule->id],
    ]);

    $invoice = $this->service->generateInvoice($admin, $dto);

    $adjustmentTypes = [
        InvoiceLineType::ADJUST_NO_SHOW->value,
        InvoiceLineType::ADJUST_CANCEL_BILLABLE->value,
        InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value,
        InvoiceLineType::ADJUST_EXTRA_SESSION->value,
        InvoiceLineType::ADJUST_RATE_DIFFERENCE->value,
        InvoiceLineType::CARRY_FORWARD_CREDIT->value,
    ];

    $adjustmentCount = $invoice->lineItems
        ->filter(fn ($item) => in_array($item->line_type->value, $adjustmentTypes, true))
        ->count();

    expect($adjustmentCount)->toBe(0);
});

function advanceScheduleConfig(School $school, int $paymentTermsDays): BillingSchedule
{
    return BillingSchedule::factory()->forSchool($school)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'payment_terms_days' => $paymentTermsDays,
        'last_period_end' => null,
    ]);
}

test('manual advance invoice due date uses the schedule payment terms', function () {
    $school = advanceSchool();
    advanceScheduleConfig($school, 14);
    $schedule = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$schedule->id],
    ]);

    $invoice = $this->service->generateInvoice($admin, $dto);

    // due_date = invoice_date (2026-06-01) + 14-day terms, not now() + 14.
    expect($invoice->due_date->toDateString())->toBe('2026-06-15');
});

test('manual advance invoice advances the schedule tracking fields', function () {
    $school = advanceSchool();
    $config = advanceScheduleConfig($school, 30);
    $schedule = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$schedule->id],
    ]);

    $this->service->generateInvoice($admin, $dto);

    $config->refresh();
    expect($config->last_period_end)->not->toBeNull()
        ->and($config->last_period_end->toDateString())->toBe('2026-06-30')
        ->and($config->last_run_at)->not->toBeNull();
});

test('re-selecting an advance draft clears the prior set and re-stamps the new selection', function () {
    $school = advanceSchool();
    $a = scheduleInPeriod($school);
    $b = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    // First selection: schedule A.
    $invoice = $this->service->generateInvoice($admin, CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$a->id],
    ]));

    expect($a->fresh()->invoice_id)->toBe($invoice->id);

    // Re-select: swap A out for B.
    $this->service->attachSessionsToDraft($invoice, AttachSessionsDTO::fromArray([
        'schedule_ids' => [$b->id],
    ]));

    expect($a->fresh()->invoice_id)->toBeNull()           // detached → billable again
        ->and($b->fresh()->invoice_id)->toBe($invoice->id) // newly stamped
        ->and($invoice->fresh()->lineItems()->count())->toBe(1);
});

test('clearing all schedules from an advance draft frees them and zeroes the invoice', function () {
    $school = advanceSchool();
    $schedule = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $invoice = $this->service->generateInvoice($admin, CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$schedule->id],
    ]));

    $this->service->attachSessionsToDraft($invoice, AttachSessionsDTO::fromArray([
        'schedule_ids' => [],
    ]));

    expect($schedule->fresh()->invoice_id)->toBeNull()
        ->and((float) $invoice->fresh()->total)->toBe(0.0)
        ->and($invoice->fresh()->lineItems()->count())->toBe(0);
});

test('advance attach data lists not-yet-invoiced and already-attached schedules', function () {
    $school = advanceSchool();
    $attached = scheduleInPeriod($school);
    $available = scheduleInPeriod($school);
    $admin = User::factory()->admin()->create();

    $invoice = $this->service->generateInvoice($admin, CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => [$attached->id],
    ]));

    $data = $this->service->getAdvanceAttachData($invoice->fresh());

    expect($data['attachedScheduleIds'])->toBe([$attached->id])
        ->and($data['rows']->pluck('id')->all())->toContain($attached->id, $available->id);

    // Both attached and available rows must carry the real charge amount (not 0).
    $rowsById = $data['rows']->keyBy(fn (array $row): int => $row['id']);
    expect($rowsById[$attached->id]['amount'])->toBe(100.0)
        ->and($rowsById[$available->id]['amount'])->toBe(100.0);
});
