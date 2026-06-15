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
        2, // 2 day min grace
        $periodEnd,
    );

    // period_end + 5 days = Mar 20, period_end + 2 grace = Mar 17, max is Mar 20
    expect($nextRun->toDateString())->toBe('2026-03-20');
});

test('calculates next run date with fixed delay respects min grace days', function () {
    $periodEnd = Carbon::parse('2026-03-15');

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::FIXED_DELAY,
        null,
        1, // 1 day delay
        5, // 5 day min grace
        $periodEnd,
    );

    // period_end + 1 day = Mar 16, period_end + 5 grace = Mar 20, max is Mar 20
    expect($nextRun->toDateString())->toBe('2026-03-20');
});

test('calculates next run date with day of week', function () {
    $periodEnd = Carbon::parse('2026-03-15'); // Sunday

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::DAY_OF_WEEK,
        2, // Tuesday
        null,
        2, // 2 day min grace
        $periodEnd,
    );

    // earliest = Mar 17 (Mon), next Tuesday after Mar 17 = Mar 17 is Mon, so Mar 18
    expect($nextRun->toDateString())->toBe('2026-03-17');
    // Wait: Mar 15 + 2 = Mar 17 (Mon), dayOfWeek=2 is Tue, so target starts at Mar 17, Mar 17 is Mon (1), add 1 = Mar 18 (Tue)
    expect($nextRun->dayOfWeek)->toBe(2); // Tuesday
});

test('calculates next run date with day of week defaults to Tuesday', function () {
    $periodEnd = Carbon::parse('2026-03-15');

    $nextRun = $this->service->calculateNextRunDate(
        GenerationDayType::DAY_OF_WEEK,
        null, // defaults to Tuesday (2)
        null,
        2,
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
