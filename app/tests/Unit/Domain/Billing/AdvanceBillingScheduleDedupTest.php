<?php

declare(strict_types=1);

use App\Domain\Billing\Services\AdvanceBillingService;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\BillingScheduleRunStatus;
use App\Enums\InvoiceLineType;
use App\Models\BillingSchedule;
use App\Models\Invoice;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Stub the rate service so every scheduled session resolves to a fixed
    // school invoice amount — keeps the dedup assertions independent of contracts.
    $this->mock(SessionLogRateService::class, function ($mock) {
        $mock->shouldReceive('calculateDualBilling')->andReturn([
            'school' => ['invoice_amount' => 100.0],
            'therapist' => ['billable_amount' => 50.0],
        ]);
    });

    $this->service = app(AdvanceBillingService::class);
});

test('buildAdvanceChargeLines excludes schedules already on an invoice', function () {
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $existingInvoice = Invoice::factory()->create(['school_id' => $school->id]);

    $common = [
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_date' => '2026-06-10',
    ];

    $unbilled = Schedule::factory()->create([...$common, 'invoice_id' => null]);
    Schedule::factory()->create([...$common, 'invoice_id' => $existingInvoice->id]);

    $lines = $this->service->buildAdvanceChargeLines(
        $school->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($lines)->toHaveCount(1)
        ->and($lines->first()->scheduleId)->toBe($unbilled->id)
        ->and($lines->first()->lineType)->toBe(InvoiceLineType::ADVANCE_SCHEDULED->value);
});

test('processing an advance schedule stamps invoice_id on the billed schedules and a re-run does not duplicate them', function () {
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');

    // Pin a 2-char state — some UsStates keys exceed invoices.school_state's
    // varchar(2), an unrelated factory/schema mismatch that would flake this test.
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    $billingSchedule = BillingSchedule::factory()->forSchool($school)->advance()->create([
        'last_period_end' => null,
    ]);

    // Seed a scheduled session on every day across this month and the next so at
    // least one lands in whatever upcoming period the run resolves — keeps the test
    // independent of today's date / semi-monthly boundary.
    $cursor = Carbon::now()->copy()->startOfMonth();
    $end = Carbon::now()->copy()->addMonth()->endOfMonth();
    while ($cursor->lessThanOrEqualTo($end)) {
        Schedule::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'schedule_date' => $cursor->toDateString(),
            'invoice_id' => null,
        ]);
        $cursor->addDay();
    }

    $result = $this->service->processAdvanceSchedule($billingSchedule);

    expect($result->status)->toBe(BillingScheduleRunStatus::SUCCESS->value)
        ->and($result->invoiceId)->not->toBeNull()
        ->and($result->sessionsFound)->toBeGreaterThan(0);

    // Every schedule charged on this invoice must be stamped with its id.
    $stamped = Schedule::query()->forInvoice($result->invoiceId)->count();
    expect($stamped)->toBe($result->sessionsFound);

    // Re-running over the same charged period must not re-include the stamped schedules.
    $rerunLines = $this->service->buildAdvanceChargeLines(
        $school->id,
        Carbon::parse($result->billingPeriodStart),
        Carbon::parse($result->billingPeriodEnd),
    );

    expect($rerunLines)->toHaveCount(0);
});
