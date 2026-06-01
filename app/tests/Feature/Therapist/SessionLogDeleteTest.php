<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;

test('therapist can delete own non-approved session log and free its schedule', function () {
    $therapist = User::factory()->therapist()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'billing_status' => BillingStatus::BILLED,
    ]);
    $sessionLog = SessionLog::factory()->draft()->withSchedule($schedule)->create();

    $response = $this->actingAs($therapist)
        ->delete(route('therapist.session-logs.destroy', $sessionLog));

    $response->assertRedirect(route('therapist.session-logs.index'));
    $this->assertSoftDeleted($sessionLog);
    expect($schedule->fresh()->billing_status)->toBe(BillingStatus::PENDING);
});

test('therapist delete responds with json when requested', function () {
    $therapist = User::factory()->therapist()->create();
    $sessionLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($therapist)
        ->deleteJson(route('therapist.session-logs.destroy', $sessionLog));

    $response->assertOk()->assertJson(['message' => 'Session log deleted successfully.']);
    $this->assertSoftDeleted($sessionLog);
});

test('therapist cannot delete another therapist session log', function () {
    $owner = User::factory()->therapist()->create();
    $other = User::factory()->therapist()->create();
    $sessionLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $owner->id,
    ]);

    // Therapists are scoped to their own logs at route-model binding, so
    // another therapist's log is not found rather than forbidden.
    $response = $this->actingAs($other)
        ->delete(route('therapist.session-logs.destroy', $sessionLog));

    $response->assertNotFound();
    $this->assertNotSoftDeleted($sessionLog);
});

test('therapist cannot delete an approved session log', function () {
    $therapist = User::factory()->therapist()->create();
    $sessionLog = SessionLog::factory()->approved()->create([
        'therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($therapist)
        ->delete(route('therapist.session-logs.destroy', $sessionLog));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($sessionLog);
});
