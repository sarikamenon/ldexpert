<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows therapist to view full calendar page', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)
        ->get(route('therapist.schedule-calendar.index'));

    $response->assertOk();
    $response->assertSee('Schedule Calendar');
    $response->assertSee('Add New Schedule');
});

it('shows active SSAs in the SSA selection modal', function () {
    $therapist = User::factory()->therapist()->create();

    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'assigned_therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.schedule-calendar.index'));

    $response->assertOk();
    $response->assertSee('Select Active SSA');
    $response->assertSee("SSA #{$ssa->id}");
});

it('disables add schedule button when therapist has no active SSAs', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)
        ->get(route('therapist.schedule-calendar.index'));

    $response->assertOk();
    $response->assertSee('No active SSAs available');
});

it('forbids admin from viewing therapist full calendar', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('therapist.schedule-calendar.index'));

    $response->assertForbidden();
});

it('forbids student from viewing therapist full calendar', function () {
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)
        ->get(route('therapist.schedule-calendar.index'));

    $response->assertForbidden();
});

it('returns only own schedules in events endpoint', function () {
    $therapist = User::factory()->therapist()->create();
    $otherTherapist = User::factory()->therapist()->create();

    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);
    Schedule::factory()->create([
        'therapist_id' => $otherTherapist->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '11:00',
        'end_time' => '12:00',
    ]);

    $response = $this->actingAs($therapist)
        ->getJson(route('therapist.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $response->assertOk();
    $events = $response->json();
    expect($events)->toHaveCount(1);
    expect($events[0]['extendedProps']['therapist_name'])->toBe($therapist->name);
});

it('returns events in fullcalendar json format for therapist', function () {
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-15',
        'start_time' => '14:00',
        'end_time' => '15:00',
        'status' => ScheduleStatus::COMPLETED,
        'billing_status' => BillingStatus::BILLED,
    ]);

    $response = $this->actingAs($therapist)
        ->getJson(route('therapist.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $schedule->id,
        'start' => '2026-03-15T14:00:00',
        'end' => '2026-03-15T15:00:00',
        'backgroundColor' => '#059669',
        'borderColor' => '#059669',
    ]);
    $response->assertJsonFragment([
        'status' => 'completed',
        'billing_status' => 'billed',
        'is_billed' => true,
    ]);
});

it('validates required start and end dates', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)
        ->getJson(route('therapist.schedule-calendar.events'));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['start', 'end']);
});

it('filters events by date range', function () {
    $therapist = User::factory()->therapist()->create();

    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
    ]);
    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-04-10',
    ]);

    $response = $this->actingAs($therapist)
        ->getJson(route('therapist.schedule-calendar.events', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $response->assertOk();
    $events = $response->json();
    expect($events)->toHaveCount(1);
});
