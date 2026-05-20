<?php

use App\Constants\UsTimezones;
use App\DTOs\CreateSchoolDTO;

it('creates dto from array and casts booleans', function () {
    $payload = [
        'full_name' => 'Full School',
        'display_name' => 'Display School',
        'address' => '123 Main',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => 1,
        'contact_first_name' => 'Jane',
        'contact_last_name' => 'Doe',
        'contact_phone' => '555-555-5555',
        'contact_email' => 'jane@example.com',
        'invoice_email' => 'billing@example.com',
        'school_type' => 'Virtual',
        'is_private_student' => true,
        'is_auto_extend' => false,
        'non_billable_scheduling' => false,
        'external_emr_name' => 'EMR X',
    ];

    $dto = CreateSchoolDTO::fromArray($payload);

    expect($dto->fullName)->toBe('Full School')
        ->and($dto->isPrivateStudent)->toBeTrue()
        ->and($dto->isAutoExtend)->toBeFalse()
        ->and($dto->nonBillableScheduling)->toBeFalse();

    expect($dto->toArray())->toMatchArray($payload);
});

it('defaults is_auto_extend to false when missing from array', function () {
    $payload = [
        'full_name' => 'Full School',
        'display_name' => 'Display School',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => 1,
        'school_type' => 'Virtual',
        'is_private_student' => true,
        'non_billable_scheduling' => false,
    ];

    $dto = CreateSchoolDTO::fromArray($payload);

    expect($dto->isAutoExtend)->toBeFalse();
});

it('defaults allow_weekend_scheduling to false when missing', function () {
    $timezone = collect(UsTimezones::TIMEZONES)->keys()->first();

    $dto = CreateSchoolDTO::fromArray([
        'full_name' => 'A', 'display_name' => 'B', 'state' => 'NY',
        'timezone' => $timezone,
        'manager_id' => 1, 'school_type' => 'Virtual',
    ]);

    expect($dto->allowWeekendScheduling)->toBeFalse()
        ->and($dto->toArray()['allow_weekend_scheduling'])->toBeFalse();
});

it('sets allow_weekend_scheduling to true when passed', function () {
    $timezone = collect(UsTimezones::TIMEZONES)->keys()->first();

    $dto = CreateSchoolDTO::fromArray([
        'full_name' => 'A', 'display_name' => 'B', 'state' => 'NY',
        'timezone' => $timezone,
        'manager_id' => 1, 'school_type' => 'Virtual',
        'allow_weekend_scheduling' => true,
    ]);

    expect($dto->allowWeekendScheduling)->toBeTrue()
        ->and($dto->toArray()['allow_weekend_scheduling'])->toBeTrue();
});

it('sets is_auto_extend to true when passed', function () {
    $payload = [
        'full_name' => 'Full School',
        'display_name' => 'Display School',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => 1,
        'school_type' => 'Virtual',
        'is_private_student' => true,
        'is_auto_extend' => true,
        'non_billable_scheduling' => false,
    ];

    $dto = CreateSchoolDTO::fromArray($payload);

    expect($dto->isAutoExtend)->toBeTrue()
        ->and($dto->toArray()['is_auto_extend'])->toBeTrue();
});
