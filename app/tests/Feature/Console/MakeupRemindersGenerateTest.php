<?php

declare(strict_types=1);

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a closure event with request_makeup=true whose date range is within
 * the default lookahead window, and an eligible schedule on that date, both
 * sharing the same school.
 *
 * @return array{0: SchoolCalendarEvent, 1: Schedule}
 */
function generatorFixture(): array
{
    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'reminder_date' => now()->addDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    $schedule = Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->addDays(5)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
    ]);

    return [$event, $schedule];
}

// ─── Happy path ─────────────────────────────────────────────────────────────

it('creates a pending reminder row for an eligible scheduled session', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);
    [$event, $schedule] = generatorFixture();

    $this->artisan('makeup-reminders:generate')->assertExitCode(0);

    $this->assertDatabaseHas('schedule_makeup_requests', [
        'school_calendar_event_id' => $event->id,
        'schedule_id' => $schedule->id,
        'status' => 'pending',
    ]);
});

it('command output mentions the created row count', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);
    generatorFixture();

    $this->artisan('makeup-reminders:generate')
        ->expectsOutputToContain('created 1 pending row')
        ->assertExitCode(0);
});

// ─── Idempotency ─────────────────────────────────────────────────────────────

it('is idempotent — running twice does not duplicate rows', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);
    generatorFixture();

    $this->artisan('makeup-reminders:generate');
    $this->artisan('makeup-reminders:generate');

    expect(ScheduleMakeupRequest::count())->toBe(1);
});

// ─── request_makeup = false ──────────────────────────────────────────────────

it('skips events where request_makeup is false', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);

    $event = SchoolCalendarEvent::factory()->withoutMakeup()->create([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);
    Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->addDays(5)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
    ]);

    $this->artisan('makeup-reminders:generate');

    expect(ScheduleMakeupRequest::count())->toBe(0);
});

// ─── Event outside lookahead window ─────────────────────────────────────────

it('skips events whose date is beyond the lookahead window', function () {
    config(['schedule_makeup.generator_lookahead_days' => 10]);

    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->addDays(15)->toDateString(),
        'end_date' => now()->addDays(15)->toDateString(),
        'reminder_date' => now()->addDays(12)->toDateString(),
        'response_date' => now()->addDays(13)->toDateString(),
    ]);
    Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->addDays(15)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
    ]);

    $this->artisan('makeup-reminders:generate');

    expect(ScheduleMakeupRequest::count())->toBe(0);
});

// ─── Multi-date closure ──────────────────────────────────────────────────────

it('creates one row per scheduled session for a multi-day closure', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);

    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),   // 3-day span
        'reminder_date' => now()->addDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    // One session on each of the three closure days
    foreach ([5, 6, 7] as $daysAhead) {
        Schedule::factory()->create([
            'school_id' => $event->school_id,
            'schedule_date' => now()->addDays($daysAhead)->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
        ]);
    }

    $this->artisan('makeup-reminders:generate');

    expect(ScheduleMakeupRequest::count())->toBe(3);
});

// ─── Batch number grouping ───────────────────────────────────────────────────

it('assigns the same batch_number to sessions for the same student and therapist across the closure', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);

    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'reminder_date' => now()->addDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    $therapist = \App\Models\User::factory()->therapist()->create();
    $student = \App\Models\User::factory()->student()->create();

    foreach ([5, 6] as $daysAhead) {
        Schedule::factory()->create([
            'school_id' => $event->school_id,
            'schedule_date' => now()->addDays($daysAhead)->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
        ]);
    }

    $this->artisan('makeup-reminders:generate');

    $batches = ScheduleMakeupRequest::pluck('batch_number')->unique();
    expect($batches)->toHaveCount(1);
});

it('assigns different batch_numbers for different therapists on the same closure', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);

    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'reminder_date' => now()->addDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    $t1 = \App\Models\User::factory()->therapist()->create();
    $t2 = \App\Models\User::factory()->therapist()->create();
    $student = \App\Models\User::factory()->student()->create();

    Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->addDays(5)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
        'therapist_id' => $t1->id,
        'student_id' => $student->id,
    ]);
    Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->addDays(5)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
        'therapist_id' => $t2->id,
        'student_id' => $student->id,
    ]);

    $this->artisan('makeup-reminders:generate');

    $batches = ScheduleMakeupRequest::pluck('batch_number')->unique();
    expect($batches)->toHaveCount(2);
});

// ─── Sub-therapist coverage ──────────────────────────────────────────────────

it('snapshots sub_therapist_id as therapist_id when sub coverage is active', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);

    $main = \App\Models\User::factory()->therapist()->create();
    $sub = \App\Models\User::factory()->therapist()->create();

    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'reminder_date' => now()->addDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->addDays(5)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
        'therapist_id' => $main->id,
        'sub_therapist_id' => $sub->id,
    ]);

    $this->artisan('makeup-reminders:generate');

    $this->assertDatabaseHas('schedule_makeup_requests', [
        'therapist_id' => $sub->id,
    ]);
});

// ─── Historical events ───────────────────────────────────────────────────────

it('skips events whose end_date is already past', function () {
    config(['schedule_makeup.generator_lookahead_days' => 30]);

    $event = SchoolCalendarEvent::factory()->create([
        'request_makeup' => true,
        'start_date' => now()->subDays(3)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
        'reminder_date' => now()->subDays(8)->toDateString(),
        'response_date' => now()->subDays(6)->toDateString(),
    ]);
    Schedule::factory()->create([
        'school_id' => $event->school_id,
        'schedule_date' => now()->subDays(3)->toDateString(),
        'status' => ScheduleStatus::SCHEDULED,
    ]);

    $this->artisan('makeup-reminders:generate');

    expect(ScheduleMakeupRequest::count())->toBe(0);
});
