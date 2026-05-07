<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scheduleCalendarAdmin(): User
{
    return User::factory()->admin()->create();
}

it('allows admin to view schedule calendar page', function () {
    $admin = scheduleCalendarAdmin();

    $response = $this->actingAs($admin)
        ->get(route('admin.schedule-calendar.index'));

    $response->assertOk();
    $response->assertSee('Schedule Calendar');
});

it('forbids therapist from viewing admin schedule calendar', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)
        ->get(route('admin.schedule-calendar.index'));

    $response->assertForbidden();
});

it('forbids student from viewing admin schedule calendar', function () {
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)
        ->get(route('admin.schedule-calendar.index'));

    $response->assertForbidden();
});

it('returns events in fullcalendar json format', function () {
    $admin = scheduleCalendarAdmin();
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'status' => ScheduleStatus::SCHEDULED,
        'billing_status' => BillingStatus::PENDING,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $schedule->id,
        'start' => '2026-03-10T09:00:00',
        'end' => '2026-03-10T10:00:00',
        'backgroundColor' => '#5563b8',
        'borderColor' => '#5563b8',
        'textColor' => '#ffffff',
    ]);
    $response->assertJsonFragment([
        'schedule_id' => $schedule->id,
        'status' => 'scheduled',
        'billing_status' => 'pending',
    ]);
});

it('filters events by a single therapist id', function () {
    $admin = scheduleCalendarAdmin();
    $therapist1 = User::factory()->therapist()->create();
    $therapist2 = User::factory()->therapist()->create();

    Schedule::factory()->create([
        'therapist_id' => $therapist1->id,
        'schedule_date' => '2026-03-10',
    ]);
    Schedule::factory()->create([
        'therapist_id' => $therapist2->id,
        'schedule_date' => '2026-03-10',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'therapist_ids' => [$therapist1->id],
        ]));

    $response->assertOk();
    $events = $response->json();
    expect($events)->toHaveCount(1);
    expect($events[0]['extendedProps']['therapist_name'])->toBe($therapist1->name);
});

it('filters events by multiple therapist ids', function () {
    $admin = scheduleCalendarAdmin();
    $therapist1 = User::factory()->therapist()->create();
    $therapist2 = User::factory()->therapist()->create();
    $therapist3 = User::factory()->therapist()->create();

    Schedule::factory()->create(['therapist_id' => $therapist1->id, 'schedule_date' => '2026-03-10']);
    Schedule::factory()->create(['therapist_id' => $therapist2->id, 'schedule_date' => '2026-03-10']);
    Schedule::factory()->create(['therapist_id' => $therapist3->id, 'schedule_date' => '2026-03-10']);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'therapist_ids' => [$therapist1->id, $therapist2->id],
        ]));

    $response->assertOk();
    $events = $response->json();
    expect($events)->toHaveCount(2);
    $therapistNames = collect($events)->pluck('extendedProps.therapist_name');
    expect($therapistNames)->toContain($therapist1->name);
    expect($therapistNames)->toContain($therapist2->name);
});

it('filters events by status', function () {
    $admin = scheduleCalendarAdmin();

    Schedule::factory()->create([
        'schedule_date' => '2026-03-10',
        'status' => ScheduleStatus::SCHEDULED,
    ]);
    Schedule::factory()->create([
        'schedule_date' => '2026-03-10',
        'status' => ScheduleStatus::COMPLETED,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'status' => 'completed',
        ]));

    $response->assertOk();
    $events = $response->json();
    expect($events)->toHaveCount(1);
    expect($events[0]['extendedProps']['status'])->toBe('completed');
});

it('filters events by billing status', function () {
    $admin = scheduleCalendarAdmin();

    Schedule::factory()->create([
        'schedule_date' => '2026-03-10',
        'billing_status' => BillingStatus::PENDING,
    ]);
    Schedule::factory()->create([
        'schedule_date' => '2026-03-10',
        'billing_status' => BillingStatus::BILLED,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'billing_status' => 'billed',
        ]));

    $response->assertOk();
    $events = $response->json();
    expect($events)->toHaveCount(1);
    expect($events[0]['extendedProps']['billing_status'])->toBe('billed');
});

it('validates required start and end dates on events endpoint', function () {
    $admin = scheduleCalendarAdmin();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.events'));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['start', 'end']);
});

it('returns schedule detail json via show endpoint', function () {
    $admin = scheduleCalendarAdmin();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    $school = School::factory()->create();
    $service = Service::factory()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
        'service_id' => $service->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'status' => ScheduleStatus::SCHEDULED,
        'billing_status' => BillingStatus::PENDING,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.show', $schedule->id));

    $response->assertOk();
    $response->assertJsonPath('schedule.id', $schedule->id);
    $response->assertJsonPath('schedule.schedule_date', '2026-03-10');
    $response->assertJsonPath('schedule.start_time', '09:00');
    $response->assertJsonPath('schedule.end_time', '10:00');
    $response->assertJsonPath('schedule.status', 'scheduled');
    $response->assertJsonPath('schedule.billing_status', 'pending');
    $response->assertJsonPath('schedule.therapist.id', $therapist->id);
    $response->assertJsonPath('schedule.therapist.name', $therapist->name);
    $response->assertJsonPath('schedule.student.id', $student->id);
    $response->assertJsonPath('schedule.service.id', $service->id);
});

it('returns 404 for non-existent schedule on show endpoint', function () {
    $admin = scheduleCalendarAdmin();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.show', 99999));

    $response->assertNotFound();
});

it('show endpoint exposes the redesigned modal contract for admins', function () {
    $admin = scheduleCalendarAdmin();
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'location_details' => 'Join here: https://zoom.us/j/123',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.show', $schedule->id));

    $response->assertOk();
    // Fields the redesigned modal reads — these were missing on the admin
    // path before the migration to ScheduleDetailsResource.
    $response->assertJsonPath('schedule.meeting_link', 'https://zoom.us/j/123');
    $response->assertJsonPath('schedule.meeting_provider', 'zoom');
    $response->assertJsonPath('schedule.is_recurring', false);
    $response->assertJsonPath('schedule.duration_formatted', '1h');
    $response->assertJsonStructure([
        'schedule' => [
            'reference', 'updated_at_formatted', 'timezone_label',
            'student' => ['timezone_label'],
        ],
    ]);
});

it('show endpoint routes session log url to admin namespace for admin viewer', function () {
    $admin = scheduleCalendarAdmin();
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $sessionLog = \App\Models\SessionLog::factory()->create([
        'schedule_id' => $schedule->id,
        'therapist_id' => $therapist->id,
        'student_id' => $schedule->student_id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schedule-calendar.show', $schedule->id));

    $response->assertOk();
    $response->assertJsonPath(
        'schedule.session_log.url',
        route('admin.session-logs.show', $sessionLog),
    );
});
