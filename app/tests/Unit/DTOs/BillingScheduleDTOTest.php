<?php

declare(strict_types=1);

use App\DTOs\BillingScheduleDTO;

test('billing schedule dto creates from array with all fields', function () {
    $data = [
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => 1,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'advance',
        'frequency' => 'monthly',
        'generation_day_type' => 'day_of_week',
        'generation_day_of_week' => 2,
        'generation_delay_days' => null,
        'min_grace_days' => 3,
        'payment_terms_days' => 45,
        'auto_generate' => true,
        'auto_send' => true,
        'notes' => 'Test notes',
    ];

    $dto = BillingScheduleDTO::fromArray($data);

    expect($dto->schedulableType)->toBe('App\\Models\\School')
        ->and($dto->schedulableId)->toBe(1)
        ->and($dto->scheduleType)->toBe('school_invoice')
        ->and($dto->billingMode)->toBe('advance')
        ->and($dto->frequency)->toBe('monthly')
        ->and($dto->generationDayType)->toBe('day_of_week')
        ->and($dto->generationDayOfWeek)->toBe(2)
        ->and($dto->generationDelayDays)->toBeNull()
        ->and($dto->minGraceDays)->toBe(3)
        ->and($dto->paymentTermsDays)->toBe(45)
        ->and($dto->autoGenerate)->toBeTrue()
        ->and($dto->autoSend)->toBeTrue()
        ->and($dto->notes)->toBe('Test notes');
});

test('billing schedule dto uses defaults for optional fields', function () {
    $data = [
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => 1,
        'schedule_type' => 'school_invoice',
        'generation_day_type' => 'day_of_week',
    ];

    $dto = BillingScheduleDTO::fromArray($data);

    expect($dto->billingMode)->toBe('standard')
        ->and($dto->frequency)->toBe('semi_monthly')
        ->and($dto->minGraceDays)->toBe(2)
        ->and($dto->paymentTermsDays)->toBe(30)
        ->and($dto->autoGenerate)->toBeTrue()
        ->and($dto->autoSend)->toBeFalse()
        ->and($dto->notes)->toBeNull();
});

test('billing schedule dto round-trips via toArray', function () {
    $data = [
        'schedulable_type' => 'App\\Models\\User',
        'schedulable_id' => 5,
        'schedule_type' => 'therapist_bill',
        'billing_mode' => 'standard',
        'frequency' => 'weekly',
        'generation_day_type' => 'fixed_delay',
        'generation_day_of_week' => null,
        'generation_delay_days' => 5,
        'min_grace_days' => 1,
        'payment_terms_days' => 15,
        'auto_generate' => false,
        'auto_send' => false,
        'notes' => null,
    ];

    $dto = BillingScheduleDTO::fromArray($data);
    $array = $dto->toArray();

    expect($array)->toMatchArray($data);
});
