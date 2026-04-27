<?php

use App\DTOs\UpdateSchoolDTO;

it('updates dto from array', function () {
    $payload = [
        'full_name' => 'Updated Full',
        'display_name' => 'Updated Display',
        'address' => '456 Elm',
        'state' => 'NY',
        'timezone' => 'America/New_York',
        'manager_id' => 2,
        'contact_first_name' => 'John',
        'contact_last_name' => 'Smith',
        'contact_phone' => '555-444-3333',
        'contact_email' => 'john@example.com',
        'invoice_email' => 'invoice@example.com',
        'school_type' => 'Blended',
        'is_private_student' => false,
        'is_auto_extend' => false,
        'non_billable_scheduling' => true,
        'external_emr_name' => 'EMR Y',
    ];

    $dto = UpdateSchoolDTO::fromArray($payload);

    expect($dto->managerId)->toBe(2)
        ->and($dto->isAutoExtend)->toBeFalse()
        ->and($dto->nonBillableScheduling)->toBeTrue()
        ->and($dto->toArray())->toMatchArray($payload);
});

it('defaults is_auto_extend to false when missing from array', function () {
    $payload = [
        'full_name' => 'Updated Full',
        'display_name' => 'Updated Display',
        'state' => 'NY',
        'timezone' => 'America/New_York',
        'manager_id' => 2,
        'school_type' => 'Blended',
        'is_private_student' => false,
        'non_billable_scheduling' => true,
    ];

    $dto = UpdateSchoolDTO::fromArray($payload);

    expect($dto->isAutoExtend)->toBeFalse();
});

it('sets is_auto_extend to true when passed', function () {
    $payload = [
        'full_name' => 'Updated Full',
        'display_name' => 'Updated Display',
        'state' => 'NY',
        'timezone' => 'America/New_York',
        'manager_id' => 2,
        'school_type' => 'Blended',
        'is_private_student' => true,
        'is_auto_extend' => true,
        'non_billable_scheduling' => false,
    ];

    $dto = UpdateSchoolDTO::fromArray($payload);

    expect($dto->isAutoExtend)->toBeTrue()
        ->and($dto->toArray()['is_auto_extend'])->toBeTrue();
});
