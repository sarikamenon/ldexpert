<?php

declare(strict_types=1);

use App\DTOs\CreateSSAGoalDTO;
use App\Enums\SSAGoalStatus;

it('creates dto from array with all fields', function () {
    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => '7',
        'student_id' => '3',
        'number' => '1.1',
        'objective' => 'Student will improve articulation.',
        'progress' => 'Making steady progress.',
    ]);

    expect($dto->ssaId)->toBe(7)
        ->and($dto->studentId)->toBe(3)
        ->and($dto->number)->toBe('1.1')
        ->and($dto->objective)->toBe('Student will improve articulation.')
        ->and($dto->progress)->toBe('Making steady progress.');
});

it('casts ids to int from string inputs', function () {
    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => '42',
        'student_id' => '99',
        'number' => '2',
        'objective' => 'Some objective.',
        'progress' => null,
    ]);

    expect($dto->ssaId)->toBeInt()->toBe(42)
        ->and($dto->studentId)->toBeInt()->toBe(99);
});

it('sets progress to null when not supplied', function () {
    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => 1,
        'student_id' => 2,
        'number' => '1',
        'objective' => 'Objective text.',
    ]);

    expect($dto->progress)->toBeNull();
});

it('sets progress to null when supplied as empty string', function () {
    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => 1,
        'student_id' => 2,
        'number' => '1',
        'objective' => 'Objective text.',
        'progress' => '',
    ]);

    expect($dto->progress)->toBeNull();
});

it('serialises to array with active status', function () {
    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => 5,
        'student_id' => 10,
        'number' => '3a',
        'objective' => 'An objective.',
        'progress' => 'Some progress.',
    ]);

    expect($dto->toArray())->toBe([
        'ssa_id' => 5,
        'student_id' => 10,
        'number' => '3a',
        'objective' => 'An objective.',
        'progress' => 'Some progress.',
        'status' => SSAGoalStatus::ACTIVE->value,
    ]);
});
