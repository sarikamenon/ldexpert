<?php

declare(strict_types=1);

use App\Enums\SSAGoalStatus;

test('status predicates match backing values', function () {
    expect(SSAGoalStatus::ACTIVE->isActive())->toBeTrue()
        ->and(SSAGoalStatus::ACTIVE->isMastered())->toBeFalse()
        ->and(SSAGoalStatus::ACTIVE->isDiscontinued())->toBeFalse()
        ->and(SSAGoalStatus::MASTERED->isMastered())->toBeTrue()
        ->and(SSAGoalStatus::MASTERED->isActive())->toBeFalse()
        ->and(SSAGoalStatus::DISCONTINUED->isDiscontinued())->toBeTrue()
        ->and(SSAGoalStatus::DISCONTINUED->isActive())->toBeFalse();
});

test('slug returns the stored enum value', function () {
    expect(SSAGoalStatus::ACTIVE->slug())->toBe('active')
        ->and(SSAGoalStatus::MASTERED->slug())->toBe('mastered')
        ->and(SSAGoalStatus::DISCONTINUED->slug())->toBe('discontinued');
});

test('options returns all string values', function () {
    expect(SSAGoalStatus::options())
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain('active', 'mastered', 'discontinued');
});

test('presentation class helpers return non-empty strings for every case', function () {
    foreach (SSAGoalStatus::cases() as $status) {
        expect($status->borderClass())->toBeString()->not->toBeEmpty()
            ->and($status->badgeClass())->toBeString()->not->toBeEmpty()
            ->and($status->dotColor())->toBeString()->not->toBeEmpty();
    }
});

test('badge variant maps to UI semantic names', function () {
    expect(SSAGoalStatus::ACTIVE->badgeVariant())->toBe('info')
        ->and(SSAGoalStatus::MASTERED->badgeVariant())->toBe('success')
        ->and(SSAGoalStatus::DISCONTINUED->badgeVariant())->toBe('muted');
});
