<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Services\MakeupSlotCalculator;
use Carbon\CarbonImmutable;

/**
 * @return array{0: CarbonImmutable, 1: CarbonImmutable}
 */
function interval(string $start, string $end): array
{
    return [
        CarbonImmutable::parse('2026-05-27 '.$start.':00', 'UTC'),
        CarbonImmutable::parse('2026-05-27 '.$end.':00', 'UTC'),
    ];
}

/**
 * @param  array<int, CarbonImmutable>  $starts
 * @return array<int, string>
 */
function asHm(array $starts): array
{
    return array_map(static fn (CarbonImmutable $s): string => $s->format('H:i'), $starts);
}

beforeEach(function () {
    $this->calc = new MakeupSlotCalculator;
});

it('returns the whole window when nothing is booked', function () {
    $starts = $this->calc->validStartTimes([interval('16:00', '17:00')], [], 60);

    expect(asHm($starts))->toBe(['16:00']);
});

it('enumerates 15-min starts that fit the duration', function () {
    $starts = $this->calc->validStartTimes([interval('16:00', '17:00')], [], 30);

    expect(asHm($starts))->toBe(['16:00', '16:15', '16:30']);
});

it('subtracts a booked slot so booked time is never offered', function () {
    // Window 16:00-19:00, 16:00-17:00 booked -> only 17:00-19:00 free.
    $starts = $this->calc->validStartTimes(
        [interval('16:00', '19:00')],
        [interval('16:00', '17:00')],
        60,
    );

    expect(asHm($starts))->toBe(['17:00', '17:15', '17:30', '17:45', '18:00']);
});

it('handles a booking in the middle, fragmenting the window', function () {
    // Window 16:00-19:00, 17:00-17:30 booked -> free 16:00-17:00 and 17:30-19:00.
    $starts = $this->calc->validStartTimes(
        [interval('16:00', '19:00')],
        [interval('17:00', '17:30')],
        60,
    );

    expect(asHm($starts))->toBe(['16:00', '17:30', '17:45', '18:00']);
});

it('returns nothing when the duration does not fit any free interval', function () {
    $starts = $this->calc->validStartTimes([interval('16:00', '16:45')], [], 60);

    expect($starts)->toBe([]);
});

it('returns nothing when the window is fully booked', function () {
    $starts = $this->calc->validStartTimes(
        [interval('16:00', '17:00')],
        [interval('16:00', '17:00')],
        30,
    );

    expect($starts)->toBe([]);
});

it('aligns the first start up to the next 15-min boundary', function () {
    // Free interval starts at 16:05 -> first valid start is 16:15.
    $starts = $this->calc->validStartTimes(
        [interval('16:00', '18:00')],
        [interval('16:00', '16:05')],
        60,
    );

    expect(asHm($starts)[0])->toBe('16:15');
});

it('merges overlapping windows before subtracting', function () {
    // Two overlapping windows collapse to 16:00-18:00.
    $starts = $this->calc->validStartTimes(
        [interval('16:00', '17:00'), interval('16:30', '18:00')],
        [],
        120,
    );

    expect(asHm($starts))->toBe(['16:00']);
});

it('subtracts overlapping busy intervals as a union', function () {
    // Busy 16:00-17:00 and 16:30-17:30 union to 16:00-17:30 -> free 17:30-19:00.
    $starts = $this->calc->validStartTimes(
        [interval('16:00', '19:00')],
        [interval('16:00', '17:00'), interval('16:30', '17:30')],
        60,
    );

    expect(asHm($starts))->toBe(['17:30', '17:45', '18:00']);
});

it('ignores zero/negative duration', function () {
    expect($this->calc->validStartTimes([interval('16:00', '17:00')], [], 0))->toBe([])
        ->and($this->calc->validStartTimes([interval('16:00', '17:00')], [], -30))->toBe([]);
});

it('computes free intervals independent of duration', function () {
    $free = $this->calc->freeIntervals(
        [interval('16:00', '19:00')],
        [interval('17:00', '17:30')],
    );

    $asPairs = array_map(
        static fn (array $i): string => $i[0]->format('H:i').'-'.$i[1]->format('H:i'),
        $free,
    );

    expect($asPairs)->toBe(['16:00-17:00', '17:30-19:00']);
});
