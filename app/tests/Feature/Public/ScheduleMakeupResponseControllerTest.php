<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * Build a sent batch of one row, ready to accept a response.
 *
 * @return array{0: ScheduleMakeupRequest, 1: string, 2: string} [row, token, batch_number]
 */
function sentBatchFixture(array $overrides = []): array
{
    $token = str_repeat('a', 64);
    $bn = 'MR_'.str_repeat('a', 29);

    $row = ScheduleMakeupRequest::factory()->sent()->create(array_merge([
        'response_token' => $token,
        'batch_number' => $bn,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ], $overrides));

    // Ensure student has a StudentProfile so TherapistMakeupNotificationService
    // ::studentDisplayName() doesn't throw when decline/request notifications fire.
    StudentProfile::factory()->create([
        'user_id' => $row->student_id,
        'first_name' => 'Test',
        'last_name' => 'Student',
    ]);

    return [$row, $token, $bn];
}

/** Build a signed URL for the given named route + token. */
function signedMakeupUrl(string $routeName, string $token): string
{
    return URL::signedRoute($routeName, ['token' => $token]);
}

// ─── Decline route (two-step: GET confirm → POST submit) ─────────────────────

it('decline GET renders a confirmation page without changing state', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture();

    $url = signedMakeupUrl('makeup-response.decline', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.decline-confirm');
    // GET is read-only: an email link-scanner hitting it must NOT decline the batch.
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT);
});

it('decline POST flips batch to DECLINED and renders declined view', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture();

    $url = signedMakeupUrl('makeup-response.decline.submit', $token);
    $response = $this->post($url);

    $response->assertOk()->assertViewIs('public.makeup-response.declined');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::DECLINED);
});

it('decline POST returns 404 on a second submit (batch no longer unresponded)', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture();

    $url = signedMakeupUrl('makeup-response.decline.submit', $token);
    $this->post($url); // first submit declines
    $response = $this->post($url); // second — batch gone from unresponded scope

    $response->assertNotFound();
});

it('decline GET renders deadline-passed view when response_date is in the past', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture([
        'response_date' => now()->subDay()->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.decline', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.deadline-passed');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT); // unchanged
});

it('decline GET renders event-past view when all event_dates are in the past', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture([
        'event_date' => now()->subDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.decline', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.event-past');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT);
});

it('decline GET returns 404 for an unknown token', function () {
    $url = signedMakeupUrl('makeup-response.decline', str_repeat('z', 64));
    $this->get($url)->assertNotFound();
});

// ─── Signed route tamper protection ─────────────────────────────────────────

it('decline returns 403 when URL signature is tampered', function () {
    [$row, $token] = sentBatchFixture();

    // Build a valid signed URL then mutate the token in the path
    $url = signedMakeupUrl('makeup-response.decline', $token);
    $tampered = str_replace($token, str_repeat('b', 64), $url);

    $this->get($tampered)->assertStatus(403);
});

it('request returns 403 when URL signature is tampered', function () {
    [$row, $token] = sentBatchFixture();

    $url = signedMakeupUrl('makeup-response.request', $token);
    $tampered = str_replace($token, str_repeat('c', 64), $url);

    $this->get($tampered)->assertStatus(403);
});

// ─── Request route — Path 2 (no therapist availability) ─────────────────────

it('request route Path 2: records REQUESTED and renders request-recorded view when therapist has no availability', function () {
    Mail::fake();

    // Ensure no availability windows exist for the therapist
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    // Update auto-created profile (User::factory()->student() creates one)
    StudentProfile::where('user_id', $student->id)->update([
        'first_name' => 'Jane',
        'last_name' => 'Parent',
        'parent_guardian_name' => 'Jane Parent',
    ]);

    $token = str_repeat('p', 64);
    $bn = 'MR_'.str_repeat('p', 29);

    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'response_token' => $token,
        'batch_number' => $bn,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.request', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.request-recorded');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::REQUESTED);
});

it('request route Path 2: renders deadline-passed view when response_date is past', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture([
        'response_date' => now()->subDay()->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.request', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.deadline-passed');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT);
});

it('request route Path 2: returns 404 on second click (batch no longer unresponded)', function () {
    Mail::fake();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update([
        'first_name' => 'Jane',
        'last_name' => 'Parent',
        'parent_guardian_name' => 'Jane Parent',
    ]);

    $token = str_repeat('q', 64);
    $bn = 'MR_'.str_repeat('q', 29);

    ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'response_token' => $token,
        'batch_number' => $bn,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.request', $token);
    $this->get($url); // first click → REQUESTED
    $response = $this->get($url); // second click — row no longer unresponded → 404

    $response->assertNotFound();
});

it('request route returns 404 for an unknown token', function () {
    $url = signedMakeupUrl('makeup-response.request', str_repeat('z', 64));
    $this->get($url)->assertNotFound();
});

// ─── Request route — Path 1 (therapist has availability) ────────────────────

it('request route Path 1: shows slot-picker when therapist has availability', function () {
    Mail::fake();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update([
        'first_name' => 'Jane',
        'last_name' => 'Student',
    ]);

    // Create an availability window for the therapist from today
    \App\Models\ScheduleMakeupAvailability::factory()->create([
        'therapist_id' => $therapist->id,
        'availability_date' => now()->addDays(7)->toDateString(),
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
    ]);

    $token = str_repeat('r', 64);
    $bn = 'MR_'.str_repeat('r', 29);

    ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'response_token' => $token,
        'batch_number' => $bn,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.request', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.slot-picker');
});

// ─── pick-slots (POST) — missing slot selection ──────────────────────────────

it('pick-slots re-renders slot-picker with error when no slot selected', function () {
    Mail::fake();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update([
        'first_name' => 'Jane',
        'last_name' => 'Student',
    ]);

    \App\Models\ScheduleMakeupAvailability::factory()->create([
        'therapist_id' => $therapist->id,
        'availability_date' => now()->addDays(7)->toDateString(),
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
    ]);

    $token = str_repeat('s', 64);
    $bn = 'MR_'.str_repeat('s', 29);

    ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'response_token' => $token,
        'batch_number' => $bn,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.pick-slots', $token);
    $response = $this->post($url, ['slots' => []]);

    $response->assertOk()->assertViewIs('public.makeup-response.slot-picker');
});

// ─── pick-slots (POST) — Path 1 booking commit ───────────────────────────────

/**
 * Build a full Path-1 bookable scenario: therapist with a 14:00–16:00 availability
 * window on the event date, a student (with parent) whose 60-min missed session sits
 * at 09:00 (outside the window), and a SENT make-up request linking them.
 *
 * @return array{0: ScheduleMakeupRequest, 1: string, 2: Schedule, 3: User, 4: string} [row, token, schedule, student, eventDate]
 */
function path1BookableFixture(string $seed): array
{
    $eventDate = now()->addDays(7)->toDateString();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update([
        'first_name' => 'Jane',
        'last_name' => 'Student',
    ]);

    \App\Models\ScheduleMakeupAvailability::factory()->create([
        'therapist_id' => $therapist->id,
        'availability_date' => $eventDate,
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_date' => $eventDate,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $token = str_repeat($seed, 64);
    $bn = 'MR_'.str_repeat($seed, 29);

    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'response_token' => $token,
        'batch_number' => $bn,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => $eventDate,
    ]);

    return [$row, $token, $schedule, $student, $eventDate];
}

it('pick-slots books a valid slot: row SCHEDULED, schedule rescheduled in place, student recorded as actor', function () {
    Mail::fake();
    [$row, $token, $schedule, $student, $eventDate] = path1BookableFixture('t');

    // 14:00 is a valid 15-min-aligned start inside the window, and the 60-min
    // session fits before 16:00.
    $start = $eventDate.' 14:00:00';

    $url = signedMakeupUrl('makeup-response.pick-slots', $token);
    $response = $this->post($url, ['slots' => [$row->id => $start]]);

    $response->assertOk()->assertViewIs('public.makeup-response.slots-booked');

    // Students have no separate parent user row here, so the student id is the actor.
    $fresh = $row->fresh();
    expect($fresh->status)->toBe(ScheduleMakeupRequestStatus::SCHEDULED)
        ->and($fresh->makeup_schedule_id)->toBe($schedule->id)
        ->and($fresh->responded_by_user_id)->toBe($student->id);

    // The missed schedule is rescheduled in place to the chosen slot.
    $schedule->refresh();
    expect($schedule->schedule_date->toDateString())->toBe($eventDate)
        ->and($schedule->start_time->format('H:i'))->toBe('14:00')
        ->and($schedule->updated_by)->toBe($student->id);

    Mail::assertSent(\App\Mail\ScheduleMakeup\TherapistMakeupScheduledMail::class);
});

it('pick-slots rejects an out-of-window time and re-renders the picker without booking', function () {
    Mail::fake();
    [$row, $token, $schedule, , $eventDate] = path1BookableFixture('u');

    // 20:00 is NOT inside the therapist's 14:00–16:00 window — the server must reject it
    // even though no other schedule collides (the arbitrary-time hole).
    $start = $eventDate.' 20:00:00';

    $url = signedMakeupUrl('makeup-response.pick-slots', $token);
    $response = $this->post($url, ['slots' => [$row->id => $start]]);

    $response->assertOk()->assertViewIs('public.makeup-response.slot-picker');

    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT)
        ->and($row->fresh()->makeup_schedule_id)->toBeNull();

    // Schedule was not moved to the rejected time.
    $schedule->refresh();
    expect($schedule->start_time->format('H:i'))->toBe('09:00');
});
