<?php

declare(strict_types=1);

use App\Enums\SessionOutcome;

test('session outcome has expected cases and labels', function () {
    expect(SessionOutcome::cases())->toHaveCount(5)
        ->and(SessionOutcome::SERVICES_ADMINISTERED->value)->toBe('services_administered')
        ->and(SessionOutcome::NO_SHOW->value)->toBe('no_show')
        ->and(SessionOutcome::BILLABLE_CANCELLATION->value)->toBe('billable_cancellation')
        ->and(SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT->value)->toBe('non_billable_cancellation_client')
        ->and(SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER->value)->toBe('non_billable_cancellation_provider');
});

test('session outcome values returns all string values', function () {
    expect(SessionOutcome::values())
        ->toBeArray()
        ->toHaveCount(5)
        ->toContain('services_administered', 'no_show', 'billable_cancellation');
});

test('chart color key returns a non-empty semantic key for every case', function () {
    foreach (SessionOutcome::cases() as $outcome) {
        expect($outcome->chartColorKey())
            ->toBeString()
            ->not->toBeEmpty()
            ->not->toStartWith('#'); // never a raw hex; must be a semantic key
    }
});

test('chart color keys are distinct enough to render a multi-segment chart', function () {
    $keys = array_map(
        static fn (SessionOutcome $outcome): string => $outcome->chartColorKey(),
        SessionOutcome::cases()
    );

    // We allow some sharing (e.g. similar muted tones) but the primary
    // distinction — services administered vs. no-show — must be visible.
    expect($keys)->not->toBe(array_fill(0, count($keys), $keys[0]))
        ->and(SessionOutcome::SERVICES_ADMINISTERED->chartColorKey())
        ->not->toBe(SessionOutcome::NO_SHOW->chartColorKey());
});

test('billable flags match the documented matrix', function () {
    expect(SessionOutcome::SERVICES_ADMINISTERED->isBillableForSchool())->toBeTrue()
        ->and(SessionOutcome::NO_SHOW->isBillableForSchool())->toBeTrue()
        ->and(SessionOutcome::BILLABLE_CANCELLATION->isBillableForSchool())->toBeTrue()
        ->and(SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT->isBillableForSchool())->toBeFalse()
        ->and(SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER->isBillableForSchool())->toBeFalse();
});
