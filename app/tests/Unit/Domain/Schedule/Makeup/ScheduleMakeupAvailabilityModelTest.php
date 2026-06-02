<?php

declare(strict_types=1);

use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── startUtc() ─────────────────────────────────────────────────────────────

it('startUtc combines availability_date and start_time in UTC', function () {
    $window = ScheduleMakeupAvailability::factory()->create([
        'availability_date' => '2026-06-15',
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $start = $window->startUtc();

    expect($start)->toBeInstanceOf(CarbonImmutable::class)
        ->and($start->toDateTimeString())->toBe('2026-06-15 14:00:00')
        ->and($start->timezone->getName())->toBe('UTC');
});

// ─── endUtc() ───────────────────────────────────────────────────────────────

it('endUtc combines availability_date and end_time in UTC', function () {
    $window = ScheduleMakeupAvailability::factory()->create([
        'availability_date' => '2026-06-15',
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $end = $window->endUtc();

    expect($end->toDateTimeString())->toBe('2026-06-15 16:00:00')
        ->and($end->timezone->getName())->toBe('UTC');
});

it('endUtc rolls over to the next day when end_time is before start_time', function () {
    $window = ScheduleMakeupAvailability::factory()->create([
        'availability_date' => '2026-06-15',
        'start_time' => '23:00',
        'end_time' => '01:00',
    ]);

    $end = $window->endUtc();

    expect($end->toDateTimeString())->toBe('2026-06-16 01:00:00');
});

it('endUtc does not roll over when end_time equals start_time (full-day window)', function () {
    // A window where start == end would be zero-length in normal use,
    // but endUtc must NOT add a day in that edge case.
    $window = ScheduleMakeupAvailability::factory()->create([
        'availability_date' => '2026-06-15',
        'start_time' => '14:00',
        'end_time' => '14:00',
    ]);

    // When end == start (not less), no day is added.
    expect($window->endUtc()->toDateString())->toBe('2026-06-15');
});

// ─── scopeForTherapist ───────────────────────────────────────────────────────

it('scopeForTherapist returns only windows for the given therapist', function () {
    $therapist = User::factory()->therapist()->create();
    $mine = ScheduleMakeupAvailability::factory()->create(['therapist_id' => $therapist->id]);
    ScheduleMakeupAvailability::factory()->create(); // another therapist

    $ids = ScheduleMakeupAvailability::query()->forTherapist($therapist)->pluck('id');

    expect($ids)->toHaveCount(1)->toContain($mine->id);
});

// ─── therapist relation ──────────────────────────────────────────────────────

it('therapist relation resolves to the owning user', function () {
    $therapist = User::factory()->therapist()->create();
    $window = ScheduleMakeupAvailability::factory()->create(['therapist_id' => $therapist->id]);

    expect($window->therapist->id)->toBe($therapist->id);
});
