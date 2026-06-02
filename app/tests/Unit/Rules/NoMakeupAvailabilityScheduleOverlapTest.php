<?php

declare(strict_types=1);

use App\Domain\Time\UserTimezoneService;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Rules\NoMakeupAvailabilityScheduleOverlap;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Run the rule and return the failure message, or null when it passes.
 */
function runOverlapRule(User $therapist, string $date, string $startTime, string $endTime): ?string
{
    $rule = new NoMakeupAvailabilityScheduleOverlap(
        $therapist,
        $date,
        $startTime,
        app(UserTimezoneService::class),
    );

    $message = null;
    $rule->validate('end_time', $endTime, function (string $msg) use (&$message): void {
        $message = $msg;
    });

    return $message;
}

it('passes when no scheduled session overlaps the window', function () {
    $therapist = User::factory()->therapist()->create(['timezone' => 'UTC']);

    expect(runOverlapRule($therapist, '2026-07-01', '14:00', '16:00'))->toBeNull();
});

it('fails when a scheduled session overlaps the window', function () {
    $therapist = User::factory()->therapist()->create(['timezone' => 'UTC']);

    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-07-01',
        'start_time' => '14:30',
        'end_time' => '15:30',
        'status' => ScheduleStatus::SCHEDULED,
    ]);

    expect(runOverlapRule($therapist, '2026-07-01', '14:00', '16:00'))->not->toBeNull();
});

it('ignores a non-scheduled (e.g. cancelled) session', function () {
    $therapist = User::factory()->therapist()->create(['timezone' => 'UTC']);

    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-07-01',
        'start_time' => '14:30',
        'end_time' => '15:30',
        'status' => ScheduleStatus::CANCELLED,
    ]);

    expect(runOverlapRule($therapist, '2026-07-01', '14:00', '16:00'))->toBeNull();
});

it('detects an overlap when the window straddles the UTC day boundary', function () {
    // NY (UTC-4 in July) therapist: 19:00–22:00 local on 2026-07-01 →
    // 2026-07-01 23:00 UTC … 2026-07-02 02:00 UTC. The conflicting session sits on
    // the *second* UTC date, which the old single-date predicate missed entirely.
    $therapist = User::factory()->therapist()->create(['timezone' => 'America/New_York']);

    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-07-02',
        'start_time' => '00:00',
        'end_time' => '01:00',
        'status' => ScheduleStatus::SCHEDULED,
    ]);

    expect(runOverlapRule($therapist, '2026-07-01', '19:00', '22:00'))->not->toBeNull();
});
