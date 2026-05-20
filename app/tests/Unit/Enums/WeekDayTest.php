<?php

declare(strict_types=1);

use App\Enums\WeekDay;

it('includes saturday and sunday cases', function () {
    expect(WeekDay::SATURDAY->value)->toBe('saturday')
        ->and(WeekDay::SUNDAY->value)->toBe('sunday');
});

it('labels weekend days correctly', function () {
    expect(WeekDay::SATURDAY->label())->toBe('Saturday')
        ->and(WeekDay::SUNDAY->label())->toBe('Sunday')
        ->and(WeekDay::SATURDAY->shortLabel())->toBe('Sat')
        ->and(WeekDay::SUNDAY->shortLabel())->toBe('Sun');
});

it('reports weekend status', function () {
    expect(WeekDay::SATURDAY->isWeekend())->toBeTrue()
        ->and(WeekDay::SUNDAY->isWeekend())->toBeTrue()
        ->and(WeekDay::MONDAY->isWeekend())->toBeFalse()
        ->and(WeekDay::FRIDAY->isWeekend())->toBeFalse();
});
