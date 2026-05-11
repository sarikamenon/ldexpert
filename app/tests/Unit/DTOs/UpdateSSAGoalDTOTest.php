<?php

declare(strict_types=1);

use App\DTOs\UpdateSSAGoalDTO;

it('creates dto from array with all fields', function () {
    $dto = UpdateSSAGoalDTO::fromArray([
        'number' => '2b',
        'objective' => 'Revised objective.',
        'progress' => '80% accuracy achieved.',
    ]);

    expect($dto->number)->toBe('2b')
        ->and($dto->objective)->toBe('Revised objective.')
        ->and($dto->progress)->toBe('80% accuracy achieved.');
});

it('sets progress to null when not supplied', function () {
    $dto = UpdateSSAGoalDTO::fromArray([
        'number' => '1',
        'objective' => 'Objective text.',
    ]);

    expect($dto->progress)->toBeNull();
});

it('sets progress to null when supplied as empty string', function () {
    $dto = UpdateSSAGoalDTO::fromArray([
        'number' => '1',
        'objective' => 'Objective text.',
        'progress' => '',
    ]);

    expect($dto->progress)->toBeNull();
});

it('serialises to array preserving null progress', function () {
    $dto = UpdateSSAGoalDTO::fromArray([
        'number' => '4',
        'objective' => 'Some objective.',
        'progress' => null,
    ]);

    expect($dto->toArray())->toBe([
        'number' => '4',
        'objective' => 'Some objective.',
        'progress' => null,
    ]);
});

it('serialises to array with progress text', function () {
    $dto = UpdateSSAGoalDTO::fromArray([
        'number' => '5',
        'objective' => 'Another objective.',
        'progress' => 'Progress note here.',
    ]);

    expect($dto->toArray())->toBe([
        'number' => '5',
        'objective' => 'Another objective.',
        'progress' => 'Progress note here.',
    ]);
});
