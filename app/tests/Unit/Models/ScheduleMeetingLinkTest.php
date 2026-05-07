<?php

declare(strict_types=1);

use App\Models\Schedule;

it('returns null meeting link when location_details is empty or null', function () {
    expect((new Schedule(['location_details' => null]))->meetingLink())->toBeNull();
    expect((new Schedule(['location_details' => '']))->meetingLink())->toBeNull();
    expect((new Schedule(['location_details' => '   ']))->meetingLink())->toBeNull();
});

it('returns null meeting link when location_details has no URL', function () {
    $schedule = new Schedule(['location_details' => 'Room 12, ground floor.']);

    expect($schedule->meetingLink())->toBeNull();
});

it('extracts the first http(s) URL from location_details', function () {
    $schedule = new Schedule([
        'location_details' => "Join here: https://zoom.us/j/123456789\nPasscode: 4242",
    ]);

    expect($schedule->meetingLink())->toBe('https://zoom.us/j/123456789');
});

it('strips trailing punctuation from extracted URLs', function () {
    $schedule = new Schedule([
        'location_details' => 'Use the link (https://meet.google.com/abc-defg-hij).',
    ]);

    expect($schedule->meetingLink())->toBe('https://meet.google.com/abc-defg-hij');
});

it('identifies zoom as the meeting provider', function () {
    $schedule = new Schedule(['location_details' => 'https://us02web.zoom.us/j/999']);

    expect($schedule->meetingProvider())->toBe('zoom');
});

it('marks non-zoom links as the other provider', function () {
    $schedule = new Schedule(['location_details' => 'https://meet.google.com/abc-defg-hij']);

    expect($schedule->meetingProvider())->toBe('other');
});

it('returns null meeting provider when there is no link', function () {
    $schedule = new Schedule(['location_details' => 'Room 12']);

    expect($schedule->meetingProvider())->toBeNull();
});
