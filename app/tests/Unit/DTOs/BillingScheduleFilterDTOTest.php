<?php

declare(strict_types=1);

use App\DTOs\BillingScheduleFilterDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;

test('billing schedule filter dto creates from array with all filters', function () {
    $data = [
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'advance',
        'is_active' => '1',
        'frequency' => 'monthly',
        'per_page' => 25,
    ];

    $dto = BillingScheduleFilterDTO::fromArray($data);

    expect($dto->scheduleType)->toBe(BillingScheduleType::SCHOOL_INVOICE)
        ->and($dto->billingMode)->toBe(BillingMode::ADVANCE)
        ->and($dto->isActive)->toBeTrue()
        ->and($dto->frequency)->toBe('monthly')
        ->and($dto->perPage)->toBe(25);
});

test('billing schedule filter dto defaults to null for all filters', function () {
    $dto = BillingScheduleFilterDTO::fromArray([]);

    expect($dto->scheduleType)->toBeNull()
        ->and($dto->billingMode)->toBeNull()
        ->and($dto->isActive)->toBeNull()
        ->and($dto->frequency)->toBeNull()
        ->and($dto->perPage)->toBe(15);
});

test('billing schedule filter dto serializes to array', function () {
    $dto = new BillingScheduleFilterDTO(
        scheduleType: BillingScheduleType::THERAPIST_BILL,
        billingMode: BillingMode::STANDARD,
        isActive: false,
        frequency: 'bi_weekly',
        perPage: 50,
    );

    $array = $dto->toArray();

    expect($array)->toMatchArray([
        'schedule_type' => 'therapist_bill',
        'billing_mode' => 'standard',
        'is_active' => false,
        'frequency' => 'bi_weekly',
        'per_page' => 50,
    ]);
});
