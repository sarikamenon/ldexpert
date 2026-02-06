<?php

use App\Enums\SchoolCalendarEventType;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calendarAdminUser(): User
{
    return User::factory()->admin()->create();
}

it('allows admin to list school calendar events', function () {
    $admin = calendarAdminUser();
    $school = School::factory()->create();
    $event = SchoolCalendarEvent::factory()->holiday()->create([
        'school_id' => $school->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-02',
        'title' => 'New Year Break',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.schools.calendar-events.index', [
            'school' => $school,
            'start' => '2026-01-01',
            'end' => '2026-01-31',
        ]));

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $event->id,
            'title' => 'New Year Break',
            'event_type' => SchoolCalendarEventType::HOLIDAY->value,
        ]);
});

it('allows admin to create update and delete school calendar events', function () {
    $admin = calendarAdminUser();
    $school = School::factory()->create();

    $payload = [
        'title' => 'Staff Development Day',
        'event_type' => SchoolCalendarEventType::NON_HOLIDAY->value,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-10',
        'notes' => 'Training day.',
    ];

    $createResponse = $this->actingAs($admin)
        ->postJson(route('admin.schools.calendar-events.store', $school), $payload);

    $createResponse->assertStatus(201)
        ->assertJsonFragment(['title' => 'Staff Development Day']);

    $eventId = $createResponse->json('event.id');

    $this->assertDatabaseHas('school_calendar_events', [
        'id' => $eventId,
        'school_id' => $school->id,
        'title' => 'Staff Development Day',
        'event_type' => SchoolCalendarEventType::NON_HOLIDAY->value,
    ]);

    $updatePayload = [
        'title' => 'Updated Training Day',
        'event_type' => SchoolCalendarEventType::HOLIDAY->value,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-11',
        'notes' => 'Updated notes.',
    ];

    $this->actingAs($admin)
        ->putJson(route('admin.schools.calendar-events.update', ['school' => $school, 'event' => $eventId]), $updatePayload)
        ->assertOk()
        ->assertJsonFragment(['title' => 'Updated Training Day']);

    $this->assertDatabaseHas('school_calendar_events', [
        'id' => $eventId,
        'event_type' => SchoolCalendarEventType::HOLIDAY->value,
        'end_date' => '2026-02-11',
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('admin.schools.calendar-events.destroy', ['school' => $school, 'event' => $eventId]))
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertSoftDeleted('school_calendar_events', [
        'id' => $eventId,
    ]);
});

it('prevents non-admin from creating school calendar events', function () {
    $therapist = User::factory()->therapist()->create();
    $school = School::factory()->create();

    $payload = [
        'title' => 'Holiday',
        'event_type' => SchoolCalendarEventType::HOLIDAY->value,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-01',
    ];

    $this->actingAs($therapist)
        ->postJson(route('admin.schools.calendar-events.store', $school), $payload)
        ->assertForbidden();
});
