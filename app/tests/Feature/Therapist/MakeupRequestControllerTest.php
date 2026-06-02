<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\ScheduleMakeupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build an owning therapist + a sent makeup request the therapist can decline.
 *
 * @return array{0: User, 1: ScheduleMakeupRequest}
 */
function declineFixture(): array
{
    $therapist = User::factory()->therapist()->create();

    $request = ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    return [$therapist, $request];
}

// ─── index ──────────────────────────────────────────────────────────────────

it('index returns 200 for the owning therapist', function () {
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('therapist.makeup-requests.index'))
        ->assertOk();
});

it('index is forbidden for unauthenticated users', function () {
    $this->get(route('therapist.makeup-requests.index'))
        ->assertRedirect(route('login'));
});

// ─── decline — happy path ────────────────────────────────────────────────────

it('decline flips status to DECLINED with therapist attribution via JSON', function () {
    [$therapist, $request] = declineFixture();

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.decline', $request->id), [
            'reason' => 'Parent is unreachable',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Make-up request declined.']);

    $request->refresh();
    expect($request->status)->toBe(ScheduleMakeupRequestStatus::DECLINED)
        ->and($request->responded_by_type)->toBe(ScheduleMakeupRespondedByType::THERAPIST)
        ->and($request->response_source)->toBe(ScheduleMakeupResponseSource::THERAPIST_MANUAL)
        ->and($request->responded_by_user_id)->toBe($therapist->id)
        ->and($request->reason)->toBe('Parent is unreachable');
});

it('decline stores the reason on the row', function () {
    [$therapist, $request] = declineFixture();

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.decline', $request->id), [
            'reason' => 'Scheduling conflict',
        ]);

    expect($request->fresh()->reason)->toBe('Scheduling conflict');
});

// ─── decline — validation ────────────────────────────────────────────────────

it('decline returns 422 when reason is missing', function () {
    [$therapist, $request] = declineFixture();

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.decline', $request->id), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

it('decline returns 422 when reason exceeds 1000 characters', function () {
    [$therapist, $request] = declineFixture();

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.decline', $request->id), [
            'reason' => str_repeat('x', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

// ─── decline — policy ────────────────────────────────────────────────────────

it('decline is forbidden for a therapist who does not own the request', function () {
    [, $request] = declineFixture();
    $other = User::factory()->therapist()->create();

    $this->actingAs($other)
        ->postJson(route('therapist.makeup-requests.decline', $request->id), [
            'reason' => 'Not my patient',
        ])
        ->assertForbidden();
});

it('decline is forbidden for an unauthenticated user', function () {
    [, $request] = declineFixture();

    $this->postJson(route('therapist.makeup-requests.decline', $request->id), [
        'reason' => 'test',
    ])->assertUnauthorized();
});

// ─── decline — already terminal ─────────────────────────────────────────────

it('decline returns 403 when request is already in a terminal state (policy rejects)', function () {
    $therapist = User::factory()->therapist()->create();

    $request = ScheduleMakeupRequest::factory()->declined()->create([
        'therapist_id' => $therapist->id,
    ]);

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.decline', $request->id), [
            'reason' => 'late attempt',
        ])
        ->assertForbidden();
});

// ─── markNotRequired ─────────────────────────────────────────────────────────

it('markNotRequired flips a pending request to NOT_REQUIRED', function () {
    $therapist = User::factory()->therapist()->create();
    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $therapist->id,
    ]);

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.mark-not-required', $request->id), [
            'reason' => 'Student transferred',
        ])
        ->assertOk();

    expect($request->fresh()->status)->toBe(ScheduleMakeupRequestStatus::NOT_REQUIRED);
});

it('markNotRequired returns 403 when called on a sent (non-pending) request (policy rejects)', function () {
    $therapist = User::factory()->therapist()->create();
    $request = ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'responded_at' => null,
    ]);

    $this->actingAs($therapist)
        ->postJson(route('therapist.makeup-requests.mark-not-required', $request->id), [
            'reason' => 'test',
        ])
        ->assertForbidden();
});

it('markNotRequired is forbidden for a different therapist', function () {
    $owner = User::factory()->therapist()->create();
    $other = User::factory()->therapist()->create();

    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->postJson(route('therapist.makeup-requests.mark-not-required', $request->id), [
            'reason' => 'test',
        ])
        ->assertForbidden();
});

// ─── show ────────────────────────────────────────────────────────────────────

it('show returns 200 for the owning therapist', function () {
    $therapist = User::factory()->therapist()->create();
    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $therapist->id,
    ]);

    $this->actingAs($therapist)
        ->get(route('therapist.makeup-requests.show', $request->id))
        ->assertOk();
});

it('show is forbidden for a different therapist', function () {
    $owner = User::factory()->therapist()->create();
    $other = User::factory()->therapist()->create();

    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->get(route('therapist.makeup-requests.show', $request->id))
        ->assertForbidden();
});
