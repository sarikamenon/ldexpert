<?php

use App\DTOs\SchoolFilterDTO;
use App\Enums\SchoolStatus;

it('creates filter dto and casts status', function () {
    $dto = SchoolFilterDTO::fromArray([
        'search' => 'north',
        'status' => 'active',
        'show_deactivated' => true,
    ]);

    expect($dto->search)->toBe('north')
        ->and($dto->status)->toBe(SchoolStatus::ACTIVE)
        ->and($dto->includeDeactivated)->toBeTrue();
});

it('includes deactivated schools when status is not provided (All Statuses)', function () {
    $dto = SchoolFilterDTO::fromArray([
        'search' => 'test',
        'status' => '',
    ]);

    expect($dto->search)->toBe('test')
        ->and($dto->status)->toBeNull()
        ->and($dto->includeDeactivated)->toBeTrue();
});

it('includes deactivated schools when status is null', function () {
    $dto = SchoolFilterDTO::fromArray([
        'search' => 'test',
    ]);

    expect($dto->status)->toBeNull()
        ->and($dto->includeDeactivated)->toBeTrue();
});

it('respects explicit show_deactivated flag when status is provided', function () {
    $dto = SchoolFilterDTO::fromArray([
        'status' => 'active',
        'show_deactivated' => false,
    ]);

    expect($dto->status)->toBe(SchoolStatus::ACTIVE)
        ->and($dto->includeDeactivated)->toBeFalse();
});
