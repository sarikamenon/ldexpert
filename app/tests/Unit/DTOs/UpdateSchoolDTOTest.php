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
        'non_billable_scheduling' => true,
        'external_emr_name' => 'EMR Y',
    ];

    $dto = UpdateSchoolDTO::fromArray($payload);

    expect($dto->managerId)->toBe(2)
        ->and($dto->nonBillableScheduling)->toBeTrue()
        ->and($dto->toArray())->toMatchArray($payload);
});
