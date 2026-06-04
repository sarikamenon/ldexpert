<?php

declare(strict_types=1);

use App\Domain\Billing\Repositories\BillingScheduleRepositoryInterface;
use App\Domain\Billing\Services\BillingScheduleService;
use App\DTOs\BillingScheduleDTO;
use App\Enums\BillingFrequency;
use App\Enums\GenerationDayType;
use App\Models\BillingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(BillingScheduleRepositoryInterface::class);
    $this->service = new BillingScheduleService($this->repository);
});

afterEach(function () {
    Mockery::close();
});

// --- Period calculation tests ---

test('determines weekly period as Monday to Sunday', function () {
    $date = Carbon::parse('2026-03-18'); // Wednesday

    $period = $this->service->determineBillingPeriod(BillingFrequency::WEEKLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-16') // Monday
        ->and($period['end']->toDateString())->toBe('2026-03-22'); // Sunday
});

test('determines weekly period when date is Monday', function () {
    $date = Carbon::parse('2026-03-16'); // Monday

    $period = $this->service->determineBillingPeriod(BillingFrequency::WEEKLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-16')
        ->and($period['end']->toDateString())->toBe('2026-03-22');
});

test('determines weekly period when date is Sunday', function () {
    $date = Carbon::parse('2026-03-22'); // Sunday

    $period = $this->service->determineBillingPeriod(BillingFrequency::WEEKLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-16')
        ->and($period['end']->toDateString())->toBe('2026-03-22');
});

test('determines semi-monthly first half period', function () {
    $date = Carbon::parse('2026-03-10');

    $period = $this->service->determineBillingPeriod(BillingFrequency::SEMI_MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-01')
        ->and($period['end']->toDateString())->toBe('2026-03-15');
});

test('determines semi-monthly second half period', function () {
    $date = Carbon::parse('2026-03-20');

    $period = $this->service->determineBillingPeriod(BillingFrequency::SEMI_MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-16')
        ->and($period['end']->toDateString())->toBe('2026-03-31');
});

test('determines semi-monthly boundary on 15th is first half', function () {
    $date = Carbon::parse('2026-03-15');

    $period = $this->service->determineBillingPeriod(BillingFrequency::SEMI_MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-01')
        ->and($period['end']->toDateString())->toBe('2026-03-15');
});

test('determines semi-monthly boundary on 16th is second half', function () {
    $date = Carbon::parse('2026-03-16');

    $period = $this->service->determineBillingPeriod(BillingFrequency::SEMI_MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-16')
        ->and($period['end']->toDateString())->toBe('2026-03-31');
});

test('determines semi-monthly february end of month', function () {
    $date = Carbon::parse('2026-02-20');

    $period = $this->service->determineBillingPeriod(BillingFrequency::SEMI_MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-02-16')
        ->and($period['end']->toDateString())->toBe('2026-02-28');
});

test('determines monthly period', function () {
    $date = Carbon::parse('2026-03-15');

    $period = $this->service->determineBillingPeriod(BillingFrequency::MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-03-01')
        ->and($period['end']->toDateString())->toBe('2026-03-31');
});

test('determines monthly period february', function () {
    $date = Carbon::parse('2026-02-10');

    $period = $this->service->determineBillingPeriod(BillingFrequency::MONTHLY, $date);

    expect($period['start']->toDateString())->toBe('2026-02-01')
        ->and($period['end']->toDateString())->toBe('2026-02-28');
});

test('determines bi-weekly period aligned to epoch', function () {
    // Epoch is 2026-01-05 (Monday). First 2-week period: Jan 5–18, second: Jan 19–Feb 1, etc.
    $date = Carbon::parse('2026-01-10'); // Saturday of first bi-week

    $period = $this->service->determineBillingPeriod(BillingFrequency::BI_WEEKLY, $date);

    expect($period['start']->toDateString())->toBe('2026-01-05')
        ->and($period['end']->toDateString())->toBe('2026-01-18');
});

test('determines bi-weekly second period', function () {
    $date = Carbon::parse('2026-01-20'); // Tuesday of second bi-week

    $period = $this->service->determineBillingPeriod(BillingFrequency::BI_WEEKLY, $date);

    expect($period['start']->toDateString())->toBe('2026-01-19')
        ->and($period['end']->toDateString())->toBe('2026-02-01');
});

// --- Next run date calculation tests ---

test('calculates next run date with fixed delay', function () {
    $periodEnd = Carbon::parse('2026-03-15');

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::FIXED_DELAY,
        null,
        5, // 5 day delay
        $periodEnd,
    );

    // period_end + 5 days = Mar 20
    expect($nextRun->toDateString())->toBe('2026-03-20');
});

test('calculates next run date with fixed delay of zero generates the next day', function () {
    $periodEnd = Carbon::parse('2026-03-15');

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::FIXED_DELAY,
        null,
        0, // 0 day delay means the next day, never same-day
        $periodEnd,
    );

    // period_end + max(0, 1) = Mar 16, never Mar 15
    expect($nextRun->toDateString())->toBe('2026-03-16');
});

test('calculates next run date with day of week walks from the period end', function () {
    $periodEnd = Carbon::parse('2026-03-15'); // Sunday

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::DAY_OF_WEEK,
        2, // Tuesday
        null,
        $periodEnd,
    );

    // No grace floor: walk forward from Mar 15 (Sun) to the next Tuesday = Mar 17
    expect($nextRun->toDateString())->toBe('2026-03-17');
    expect($nextRun->dayOfWeek)->toBe(2); // Tuesday
});

test('calculates next run date with day of week defaults to Tuesday', function () {
    $periodEnd = Carbon::parse('2026-03-15');

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::DAY_OF_WEEK,
        null, // defaults to Tuesday (2)
        null,
        $periodEnd,
    );

    expect($nextRun->dayOfWeek)->toBe(2); // Tuesday
});

// --- CRUD delegation tests ---

test('create schedule delegates to repository with calculated next_run_at', function () {
    $dto = BillingScheduleDTO::fromArray([
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => 1,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'standard',
        'frequency' => 'semi_monthly',
        'generation_day_type' => 'day_of_week',
        'generation_day_of_week' => 2,
        'min_grace_days' => 2,
        'payment_terms_days' => 30,
    ]);

    $expectedSchedule = new BillingSchedule;
    $expectedSchedule->id = 1;

    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(function (array $data): bool {
            return isset($data['next_run_at'])
                && $data['schedulable_type'] === 'App\\Models\\School'
                && $data['schedule_type'] === 'school_invoice';
        })
        ->andReturn($expectedSchedule);

    $result = $this->service->createSchedule($dto);

    expect($result)->toBe($expectedSchedule);
});

test('create schedule anchors the first period on billing_start_date when set', function () {
    // Semi-monthly, fixed-delay 2: start date 2026-06-16 → period 16th–30th → run 30th + 2 = Jul 2.
    $dto = BillingScheduleDTO::fromArray([
        'schedulable_type' => 'App\\Models\\User',
        'schedulable_id' => 1,
        'schedule_type' => 'therapist_bill',
        'billing_mode' => 'standard',
        'frequency' => 'semi_monthly',
        'generation_day_type' => 'fixed_delay',
        'generation_delay_days' => 2,
        'payment_terms_days' => 30,
        'billing_start_date' => '2026-06-16',
    ]);

    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn (array $data): bool => $data['next_run_at'] === '2026-07-02')
        ->andReturn(new BillingSchedule);

    $this->service->createSchedule($dto);
});

test('create schedule holds next_run_at no earlier than a future billing_start_date', function () {
    // Future start date in the same period: run date computed from the period end
    // would precede the start date, so it is clamped up to the start date.
    $dto = BillingScheduleDTO::fromArray([
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => 1,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'advance',
        'frequency' => 'monthly',
        'generation_day_type' => 'fixed_delay',
        'generation_delay_days' => 1,
        'payment_terms_days' => 30,
        'billing_start_date' => '2026-12-01', // 1st of next month, period ends 2026-12-31 (run would be Jan 1)
    ]);

    // Monthly period containing Dec 1 ends Dec 31; run = Dec 31 + 1 = 2027-01-01,
    // which is after the start date, so no clamping needed here — assert it is >= start.
    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(function (array $data): bool {
            return $data['next_run_at'] >= '2026-12-01';
        })
        ->andReturn(new BillingSchedule);

    $this->service->createSchedule($dto);
});

test('create schedule with null billing_start_date uses now-based period (legacy behavior)', function () {
    $dto = BillingScheduleDTO::fromArray([
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => 1,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'standard',
        'frequency' => 'semi_monthly',
        'generation_day_type' => 'day_of_week',
        'generation_day_of_week' => 2,
        'payment_terms_days' => 30,
        // no billing_start_date
    ]);

    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn (array $data): bool => isset($data['next_run_at']))
        ->andReturn(new BillingSchedule);

    $this->service->createSchedule($dto);
});

test('toggle active flips the is_active flag', function () {
    $schedule = new BillingSchedule;
    $schedule->is_active = true;

    $updatedSchedule = new BillingSchedule;
    $updatedSchedule->is_active = false;

    $this->repository->shouldReceive('update')
        ->once()
        ->with($schedule, ['is_active' => false])
        ->andReturn($updatedSchedule);

    $result = $this->service->toggleActive($schedule);

    expect($result->is_active)->toBeFalse();
});

test('advance schedule updates tracking fields', function () {
    $schedule = BillingSchedule::factory()->make([
        'frequency' => BillingFrequency::SEMI_MONTHLY->value,
        'generation_day_type' => GenerationDayType::DAY_OF_WEEK->value,
        'generation_day_of_week' => 2,
        'min_grace_days' => 2,
    ]);

    $periodEnd = Carbon::parse('2026-03-15');

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(function (BillingSchedule $s, array $data) {
            return isset($data['last_run_at'])
                && $data['last_period_end'] === '2026-03-15'
                && isset($data['next_run_at']);
        })
        ->andReturn($schedule);

    $this->service->advanceSchedule($schedule, $periodEnd);
});

test('advance schedule honors a stored fixed delay of zero as a one-day delay', function () {
    $schedule = BillingSchedule::factory()->make([
        'frequency' => BillingFrequency::MONTHLY->value,
        'generation_day_type' => GenerationDayType::FIXED_DELAY->value,
        'generation_day_of_week' => null,
        'generation_delay_days' => 0,
    ]);

    // Monthly period ending 2026-03-31 → next period ends 2026-04-30.
    $periodEnd = Carbon::parse('2026-03-31');

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(function (BillingSchedule $s, array $data) {
            // delay 0 → max(0, 1) = 1 day after the next period end (2026-04-30),
            // NOT the 3-day default that a falsy-zero read would produce.
            return $data['next_run_at'] === '2026-05-01';
        })
        ->andReturn($schedule);

    $this->service->advanceSchedule($schedule, $periodEnd);
});

test('determine next period end advances past last period end', function () {
    $lastPeriodEnd = Carbon::parse('2026-03-15'); // end of first semi-monthly

    $nextEnd = $this->service->determineNextPeriodEnd(BillingFrequency::SEMI_MONTHLY, $lastPeriodEnd);

    // Next day is Mar 16, which falls in second half (16-31)
    expect($nextEnd->toDateString())->toBe('2026-03-31');
});

test('determine current period end returns end of current period', function () {
    $date = Carbon::parse('2026-03-10');

    $end = $this->service->determineCurrentPeriodEnd(BillingFrequency::SEMI_MONTHLY, $date);

    expect($end->toDateString())->toBe('2026-03-15');
});
