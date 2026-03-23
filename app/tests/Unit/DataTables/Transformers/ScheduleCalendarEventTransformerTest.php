<?php

declare(strict_types=1);

use App\DataTables\Transformers\ScheduleCalendarEventTransformer;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transforms a scheduled schedule into fullcalendar event format', function () {
    $therapist = User::factory()->therapist()->create(['name' => 'Dr. Smith']);
    $student = User::factory()->student()->create(['name' => 'John Doe']);

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'status' => ScheduleStatus::SCHEDULED,
        'billing_status' => BillingStatus::PENDING,
        'is_group' => false,
    ]);

    $schedule->load(['therapist', 'student', 'service', 'school']);

    $event = ScheduleCalendarEventTransformer::transform($schedule);

    expect($event)
        ->toHaveKey('id', $schedule->id)
        ->toHaveKey('start', '2026-03-10T09:00:00')
        ->toHaveKey('end', '2026-03-10T10:00:00')
        ->toHaveKey('backgroundColor', '#5563b8')
        ->toHaveKey('borderColor', '#5563b8')
        ->toHaveKey('textColor', '#ffffff');

    expect($event['title'])->toContain('John Doe');
});

it('uses distinct colors for status and billing combinations', function () {
    $therapist = User::factory()->therapist()->create();

    // Scheduled + Pending = primary blue
    $scheduledPending = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'status' => ScheduleStatus::SCHEDULED,
        'billing_status' => BillingStatus::PENDING,
    ]);

    // Completed + Billed = green
    $completedBilled = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-11',
        'status' => ScheduleStatus::COMPLETED,
        'billing_status' => BillingStatus::BILLED,
    ]);

    // Completed + Pending = amber
    $completedPending = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-12',
        'status' => ScheduleStatus::COMPLETED,
        'billing_status' => BillingStatus::PENDING,
    ]);

    // Cancelled = gray regardless of billing
    $cancelled = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-13',
        'status' => ScheduleStatus::CANCELLED,
    ]);

    foreach ([$scheduledPending, $completedBilled, $completedPending, $cancelled] as $s) {
        $s->load(['therapist', 'student', 'service', 'school']);
    }

    expect(ScheduleCalendarEventTransformer::transform($scheduledPending)['backgroundColor'])->toBe('#5563b8');
    expect(ScheduleCalendarEventTransformer::transform($completedBilled)['backgroundColor'])->toBe('#059669');
    expect(ScheduleCalendarEventTransformer::transform($completedPending)['backgroundColor'])->toBe('#d97706');
    expect(ScheduleCalendarEventTransformer::transform($cancelled)['backgroundColor'])->toBe('#9ca3af');
});

it('matches backgroundColor and borderColor for unified event color', function () {
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'status' => ScheduleStatus::COMPLETED,
        'billing_status' => BillingStatus::BILLED,
    ]);

    $schedule->load(['therapist', 'student', 'service', 'school']);

    $event = ScheduleCalendarEventTransformer::transform($schedule);

    expect($event['backgroundColor'])->toBe($event['borderColor']);
});

it('includes all required extended props', function () {
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'status' => ScheduleStatus::SCHEDULED,
        'billing_status' => BillingStatus::PENDING,
        'is_group' => true,
    ]);

    $schedule->load(['therapist', 'student', 'service', 'school']);

    $event = ScheduleCalendarEventTransformer::transform($schedule);
    $props = $event['extendedProps'];

    expect($props)
        ->toHaveKey('schedule_id', $schedule->id)
        ->toHaveKey('therapist_name', $therapist->name)
        ->toHaveKey('status', 'scheduled')
        ->toHaveKey('billing_status', 'pending')
        ->toHaveKey('is_past')
        ->toHaveKey('is_billed', false)
        ->toHaveKey('is_group', true);
});

it('sets is_billed to true for billed schedules', function () {
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'billing_status' => BillingStatus::BILLED,
    ]);

    $schedule->load(['therapist', 'student', 'service', 'school']);

    $event = ScheduleCalendarEventTransformer::transform($schedule);

    expect($event['extendedProps']['is_billed'])->toBeTrue();
});
