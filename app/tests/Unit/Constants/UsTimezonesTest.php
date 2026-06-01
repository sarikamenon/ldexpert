<?php

declare(strict_types=1);

use App\Constants\UsTimezones;

it('resolves from input by timezone key', function () {
    expect(UsTimezones::resolveFromInput('America/New_York'))->toBe('America/New_York');
    expect(UsTimezones::resolveFromInput('America/Chicago'))->toBe('America/Chicago');
    expect(UsTimezones::resolveFromInput('Pacific/Honolulu'))->toBe('Pacific/Honolulu');
    expect(UsTimezones::resolveFromInput('Asia/Karachi'))->toBe('Asia/Karachi');
    expect(UsTimezones::resolveFromInput('Europe/Istanbul'))->toBe('Europe/Istanbul');
    expect(UsTimezones::resolveFromInput('Europe/London'))->toBe('Europe/London');
});

it('resolves from input by display label', function () {
    expect(UsTimezones::resolveFromInput('Eastern Time (ET)'))->toBe('America/New_York');
    expect(UsTimezones::resolveFromInput('Central Time (CT)'))->toBe('America/Chicago');
    expect(UsTimezones::resolveFromInput('Pacific Time (PT)'))->toBe('America/Los_Angeles');
    expect(UsTimezones::resolveFromInput('Islamabad, Karachi (UTC+5:00)'))->toBe('Asia/Karachi');
    expect(UsTimezones::resolveFromInput('Istanbul (UTC+3:00)'))->toBe('Europe/Istanbul');
});

it('returns null for empty input', function () {
    expect(UsTimezones::resolveFromInput(null))->toBeNull();
    expect(UsTimezones::resolveFromInput(''))->toBeNull();
    expect(UsTimezones::resolveFromInput('   '))->toBeNull();
});

it('returns null for invalid input', function () {
    expect(UsTimezones::resolveFromInput('Invalid/Timezone'))->toBeNull();
    expect(UsTimezones::resolveFromInput('Some Random Label'))->toBeNull();
});

it('trims whitespace before resolving', function () {
    expect(UsTimezones::resolveFromInput('  Eastern Time (ET)  '))->toBe('America/New_York');
});

it('returns a label for the new timezones', function () {
    expect(UsTimezones::getTimezoneLabel('Asia/Karachi'))->toBe('Islamabad, Karachi (UTC+5:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Istanbul'))->toBe('Istanbul (UTC+3:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/London'))->toBe('Dublin, Edinburgh, Lisbon, London (UTC+0:00)');
});
