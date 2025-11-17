<?php

use App\DTOs\ChangeSchoolStatusDTO;
use App\Enums\SchoolStatus;

it('builds change status dto from array', function () {
    $dto = ChangeSchoolStatusDTO::fromArray([
        'status' => 'inactive',
        'reason' => 'No longer active',
    ]);

    expect($dto->status)->toBe(SchoolStatus::INACTIVE)
        ->and($dto->reason)->toBe('No longer active')
        ->and($dto->toArray())->toMatchArray([
            'status' => 'inactive',
            'status_reason' => 'No longer active',
        ]);
});
