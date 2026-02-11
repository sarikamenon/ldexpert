<?php

use App\DTOs\CreateTherapistBillDTO;

test('create therapist bill dto from array', function () {
    $data = [
        'therapist_id' => 1,
        'bill_date' => '2026-01-16',
        'bill_number' => 'BILL-20260116-001',
        'billing_period_start' => '2026-01-01',
        'billing_period_end' => '2026-01-31',
        'session_log_ids' => [1, 2, 3],
        'due_date' => '2026-02-15',
        'notes' => 'Test notes',
    ];

    $dto = CreateTherapistBillDTO::fromArray($data);

    expect($dto->therapistId)->toBe(1)
        ->and($dto->billDate)->toBe('2026-01-16')
        ->and($dto->billNumber)->toBe('BILL-20260116-001')
        ->and($dto->billingPeriodStart)->toBe('2026-01-01')
        ->and($dto->billingPeriodEnd)->toBe('2026-01-31')
        ->and($dto->sessionLogIds)->toBe([1, 2, 3])
        ->and($dto->dueDate)->toBe('2026-02-15')
        ->and($dto->notes)->toBe('Test notes');
});

test('create therapist bill dto to array', function () {
    $data = [
        'therapist_id' => 1,
        'bill_date' => '2026-01-16',
        'bill_number' => 'BILL-20260116-001',
        'billing_period_start' => '2026-01-01',
        'billing_period_end' => '2026-01-31',
        'session_log_ids' => [1, 2, 3],
        'due_date' => '2026-02-15',
        'notes' => 'Test notes',
    ];

    $dto = CreateTherapistBillDTO::fromArray($data);
    $array = $dto->toArray();

    expect($array)->toMatchArray([
        'therapist_id' => 1,
        'bill_date' => '2026-01-16',
        'bill_number' => 'BILL-20260116-001',
        'billing_period_start' => '2026-01-01',
        'billing_period_end' => '2026-01-31',
        'session_log_ids' => [1, 2, 3],
        'due_date' => '2026-02-15',
        'notes' => 'Test notes',
    ]);
});
