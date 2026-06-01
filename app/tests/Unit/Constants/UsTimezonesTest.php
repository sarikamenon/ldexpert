<?php

declare(strict_types=1);

use App\Constants\UsTimezones;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

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
    expect(UsTimezones::resolveFromInput('Islamabad, Karachi'))->toBe('Asia/Karachi');
    expect(UsTimezones::resolveFromInput('Istanbul'))->toBe('Europe/Istanbul');
    expect(UsTimezones::resolveFromInput('Edinburgh, London'))->toBe('Europe/London');
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

it('labels fixed-offset international zones with their live UTC offset', function () {
    // Karachi (PKT) and Istanbul (TRT) do not observe DST, so the offset is stable.
    Carbon::setTestNow('2026-01-15 12:00:00');
    expect(UsTimezones::getTimezoneLabel('Asia/Karachi'))->toBe('Islamabad, Karachi (UTC+05:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Istanbul'))->toBe('Istanbul (UTC+03:00)');

    Carbon::setTestNow('2026-07-15 12:00:00');
    expect(UsTimezones::getTimezoneLabel('Asia/Karachi'))->toBe('Islamabad, Karachi (UTC+05:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Istanbul'))->toBe('Istanbul (UTC+03:00)');
});

it('shifts the UK & Ireland offset with daylight saving time', function () {
    // Standard time (GMT, UTC+00:00) in January.
    Carbon::setTestNow('2026-01-15 12:00:00');
    expect(UsTimezones::getTimezoneLabel('Europe/London'))->toBe('Edinburgh, London (UTC+00:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Dublin'))->toBe('Dublin (UTC+00:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Lisbon'))->toBe('Lisbon (UTC+00:00)');

    // Summer time (BST/IST/WEST, UTC+01:00) in July.
    Carbon::setTestNow('2026-07-15 12:00:00');
    expect(UsTimezones::getTimezoneLabel('Europe/London'))->toBe('Edinburgh, London (UTC+01:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Dublin'))->toBe('Dublin (UTC+01:00)');
    expect(UsTimezones::getTimezoneLabel('Europe/Lisbon'))->toBe('Lisbon (UTC+01:00)');
});

it('leaves US zone labels unchanged (no offset suffix)', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');
    expect(UsTimezones::getTimezoneLabel('America/New_York'))->toBe('Eastern Time (ET)');
    expect(UsTimezones::getTimezoneLabel('Pacific/Honolulu'))->toBe('Hawaii Time (HT)');
});
