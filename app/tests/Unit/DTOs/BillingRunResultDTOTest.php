<?php

declare(strict_types=1);

use App\DTOs\BillingRunResultDTO;

test('billing run result dto creates from array', function () {
    $data = [
        'billing_schedule_id' => 1,
        'status' => 'success',
        'billing_period_start' => '2026-03-01',
        'billing_period_end' => '2026-03-15',
        'sessions_found' => 10,
        'sessions_from_prior_periods' => 2,
        'adjustments_count' => 3,
        'adjustment_total' => -150.50,
        'carry_forward_amount' => 0,
        'invoice_id' => 42,
        'therapist_bill_id' => null,
        'total_amount' => 1500.00,
        'auto_sent' => true,
        'error_message' => null,
    ];

    $dto = BillingRunResultDTO::fromArray($data);

    expect($dto->billingScheduleId)->toBe(1)
        ->and($dto->status)->toBe('success')
        ->and($dto->billingPeriodStart)->toBe('2026-03-01')
        ->and($dto->billingPeriodEnd)->toBe('2026-03-15')
        ->and($dto->sessionsFound)->toBe(10)
        ->and($dto->sessionsFromPriorPeriods)->toBe(2)
        ->and($dto->adjustmentsCount)->toBe(3)
        ->and($dto->adjustmentTotal)->toBe(-150.50)
        ->and($dto->carryForwardAmount)->toBe(0.0)
        ->and($dto->invoiceId)->toBe(42)
        ->and($dto->therapistBillId)->toBeNull()
        ->and($dto->totalAmount)->toBe(1500.00)
        ->and($dto->autoSent)->toBeTrue()
        ->and($dto->errorMessage)->toBeNull();
});

test('billing run result dto defaults optional fields', function () {
    $data = [
        'billing_schedule_id' => 1,
        'status' => 'skipped_no_sessions',
        'billing_period_start' => '2026-03-01',
        'billing_period_end' => '2026-03-15',
    ];

    $dto = BillingRunResultDTO::fromArray($data);

    expect($dto->sessionsFound)->toBe(0)
        ->and($dto->sessionsFromPriorPeriods)->toBe(0)
        ->and($dto->adjustmentsCount)->toBe(0)
        ->and($dto->adjustmentTotal)->toBe(0.0)
        ->and($dto->carryForwardAmount)->toBe(0.0)
        ->and($dto->invoiceId)->toBeNull()
        ->and($dto->therapistBillId)->toBeNull()
        ->and($dto->totalAmount)->toBeNull()
        ->and($dto->autoSent)->toBeFalse()
        ->and($dto->errorMessage)->toBeNull();
});

test('billing run result dto round-trips via toArray', function () {
    $data = [
        'billing_schedule_id' => 5,
        'status' => 'failed',
        'billing_period_start' => '2026-02-01',
        'billing_period_end' => '2026-02-28',
        'sessions_found' => 0,
        'sessions_from_prior_periods' => 0,
        'adjustments_count' => 0,
        'adjustment_total' => 0.0,
        'carry_forward_amount' => 0.0,
        'invoice_id' => null,
        'therapist_bill_id' => null,
        'total_amount' => null,
        'auto_sent' => false,
        'error_message' => 'Something went wrong',
    ];

    $dto = BillingRunResultDTO::fromArray($data);

    expect($dto->toArray())->toMatchArray($data);
});
