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

// ─── Decline route ──────────────────────────────────────────────────────────

it('decline flips batch to DECLINED and renders declined view', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture();

    $url = signedMakeupUrl('makeup-response.decline', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.declined');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::DECLINED);
});

it('decline returns 404 on a second click (batch no longer unresponded)', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture();

    $url = signedMakeupUrl('makeup-response.decline', $token);
    $this->get($url); // first click declines
    $response = $this->get($url); // second click — batch gone from unresponded scope

    $response->assertNotFound();
});

it('decline renders deadline-passed view when response_date is in the past', function () {
    Mail::fake();
    [$row, $token] = sentBatchFixture([
        'response_date' => now()->subDay()->toDateString(),
    ]);

    $url = signedMakeupUrl('makeup-response.decline', $token);
    $response = $this->get($url);

    $response->assertOk()->assertViewIs('public.makeup-response.deadline-passed');
    expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT); // unchanged
});

it('decline renders event-past view when all event_dates are in the past', function () {
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

it('decline returns 404 for an unknown token', function () {
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
