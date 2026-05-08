<?php

declare(strict_types=1);

use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns ScheduleDetailsResource shape with required modal fields', function () {
    $therapist = User::factory()->therapist()->create(['timezone' => 'America/Los_Angeles']);
    TherapistProfile::factory()->create([
        'user_id' => $therapist->id,
        'timezone' => 'America/Los_Angeles',
    ]);

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => '2026-03-10',
        'start_time' => '20:00',
        'end_time' => '21:00',
        'location_details' => 'Join: https://zoom.us/j/abc',
    ]);

    $response = $this->actingAs($therapist)
        ->getJson(route('therapist.schedule.show', $schedule->id));

    $response->assertOk();
    $response->assertJsonPath('schedule.id', $schedule->id);
    // 20:00 UTC → 13:00 PDT — proves the controller passed the
    // therapist's timezone to the Resource.
    $response->assertJsonPath('schedule.start_time', '13:00');
    $response->assertJsonPath('schedule.timezone', 'America/Los_Angeles');
    $response->assertJsonPath('schedule.meeting_link', 'https://zoom.us/j/abc');
    $response->assertJsonPath('schedule.meeting_provider', 'zoom');
    $response->assertJsonPath('schedule.is_recurring', false);
    $response->assertJsonStructure([
        'schedule' => [
            'reference', 'duration_formatted', 'timezone_label',
            'updated_at_formatted', 'student', 'school', 'parent',
            'email_logs', 'session_log',
        ],
    ]);
});

it('routes session log url to therapist namespace', function () {
    $therapist = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
    ]);

    $sessionLog = SessionLog::factory()->create([
        'schedule_id' => $schedule->id,
        'therapist_id' => $therapist->id,
        'student_id' => $schedule->student_id,
    ]);

    $response = $this->actingAs($therapist)
        ->getJson(route('therapist.schedule.show', $schedule->id));

    $response->assertOk();
    $response->assertJsonPath(
        'schedule.session_log.url',
        route('therapist.session-logs.show', $sessionLog),
    );
});

it('returns 404 for a schedule belonging to another therapist', function () {
    $therapistA = User::factory()->therapist()->create();
    $therapistB = User::factory()->therapist()->create();

    /** @var Schedule $schedule */
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapistB->id,
    ]);

    $this->actingAs($therapistA)
        ->getJson(route('therapist.schedule.show', $schedule->id))
        ->assertNotFound();
});
