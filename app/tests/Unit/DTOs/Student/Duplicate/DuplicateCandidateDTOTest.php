<?php

declare(strict_types=1);

use App\DTOs\Student\Duplicate\DuplicateCandidateDTO;

it('builds from a full array', function () {
    $dto = DuplicateCandidateDTO::fromArray([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'parent@example.com',
        'school_id' => '7',
        'date_of_birth' => '2015-04-01',
        'grade_level' => '5',
    ]);

    expect($dto->firstName)->toBe('Jane')
        ->and($dto->lastName)->toBe('Smith')
        ->and($dto->email)->toBe('parent@example.com')
        ->and($dto->schoolId)->toBe(7)
        ->and($dto->dateOfBirth)->toBe('2015-04-01')
        ->and($dto->gradeLevel)->toBe('5');
});

it('trims names and coerces empty optionals to null', function () {
    $dto = DuplicateCandidateDTO::fromArray([
        'first_name' => '  Jane  ',
        'last_name' => '  Smith ',
        'email' => '',
        'date_of_birth' => '',
        'grade_level' => '',
    ]);

    expect($dto->firstName)->toBe('Jane')
        ->and($dto->lastName)->toBe('Smith')
        ->and($dto->email)->toBeNull()
        ->and($dto->schoolId)->toBeNull()
        ->and($dto->dateOfBirth)->toBeNull()
        ->and($dto->gradeLevel)->toBeNull();
});
