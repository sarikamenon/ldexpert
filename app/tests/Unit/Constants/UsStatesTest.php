<?php

declare(strict_types=1);

use App\Constants\UsStates;

it('returns the name for the new non-US states', function () {
    expect(UsStates::getStateName('KHI'))->toBe('Karachi');
    expect(UsStates::getStateName('ISB'))->toBe('Islamabad');
    expect(UsStates::getStateName('IST'))->toBe('Istanbul');
    expect(UsStates::getStateName('LDN'))->toBe('London');
    expect(UsStates::getStateName('EDI'))->toBe('Edinburgh');
    expect(UsStates::getStateName('DUB'))->toBe('Dublin');
    expect(UsStates::getStateName('LIS'))->toBe('Lisbon');
});

it('still returns the name for US states', function () {
    expect(UsStates::getStateName('CA'))->toBe('California');
    expect(UsStates::getStateName('NY'))->toBe('New York');
});

it('returns the code unchanged when unknown', function () {
    expect(UsStates::getStateName('ZZ'))->toBe('ZZ');
});

it('includes the new states in the keys', function () {
    expect(array_keys(UsStates::STATES))
        ->toContain('KHI', 'ISB', 'IST', 'LDN', 'EDI', 'DUB', 'LIS');
});
