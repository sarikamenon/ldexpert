<?php

declare(strict_types=1);

use App\Models\ScheduleMakeupAvailability;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A therapist whose profile/user timezone is UTC, so user-local == stored UTC
 * and assertions stay simple.
 */
function makeupAvailabilityTherapist(): User
{
    $therapist = User::factory()->therapist()->create(['timezone' => 'UTC']);
    TherapistProfile::factory()->create([
        'user_id' => $therapist->id,
        'timezone' => 'UTC',
    ]);

    return $therapist;
}

// ─── index / create (views + auth) ──────────────────────────────────────────

it('shows the availability index to its owning therapist', function () {
    $therapist = makeupAvailabilityTherapist();

    $this->actingAs($therapist)
        ->get(route('therapist.makeup-requests.availability.index'))
        ->assertOk()
        ->assertViewIs('therapist.makeup-requests.availability.index');
});

it('shows the create form to a therapist', function () {
    $therapist = makeupAvailabilityTherapist();

    $this->actingAs($therapist)
        ->get(route('therapist.makeup-requests.availability.create'))
        ->assertOk()
        ->assertViewIs('therapist.makeup-requests.availability.create');
});

// ─── store ──────────────────────────────────────────────────────────────────

it('stores an availability window for the therapist', function () {
    $therapist = makeupAvailabilityTherapist();
    $date = now()->addWeek()->toDateString();

    $this->actingAs($therapist)
        ->post(route('therapist.makeup-requests.availability.store'), [
            'availability_date' => $date,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'notes' => 'Afternoon block',
        ])
        ->assertRedirect(route('therapist.makeup-requests.availability.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('schedule_makeup_availabilities', [
        'therapist_id' => $therapist->id,
        'availability_date' => $date,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'notes' => 'Afternoon block',
    ]);
});

it('requires date, start and end time on store', function () {
    $therapist = makeupAvailabilityTherapist();

    $this->actingAs($therapist)
        ->post(route('therapist.makeup-requests.availability.store'), [])
        ->assertSessionHasErrors(['availability_date', 'start_time', 'end_time']);
});

it('rejects a window whose end time is not after start time', function () {
    $therapist = makeupAvailabilityTherapist();

    $this->actingAs($therapist)
        ->post(route('therapist.makeup-requests.availability.store'), [
            'availability_date' => now()->addWeek()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '14:00',
        ])
        ->assertSessionHasErrors(['end_time']);
});

it('rejects an availability date in the past', function () {
    $therapist = makeupAvailabilityTherapist();

    $this->actingAs($therapist)
        ->post(route('therapist.makeup-requests.availability.store'), [
            'availability_date' => now()->subDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
        ])
        ->assertSessionHasErrors(['availability_date']);
});

it('forbids a non-therapist from storing a window', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('therapist.makeup-requests.availability.store'), [
            'availability_date' => now()->addWeek()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
        ])
        ->assertForbidden();
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('lets a therapist delete its own availability window', function () {
    $therapist = makeupAvailabilityTherapist();
    $window = ScheduleMakeupAvailability::factory()->create(['therapist_id' => $therapist->id]);

    $this->actingAs($therapist)
        ->delete(route('therapist.makeup-requests.availability.destroy', $window->id))
        ->assertRedirect(route('therapist.makeup-requests.availability.index'))
        ->assertSessionHas('status');

    $this->assertSoftDeleted('schedule_makeup_availabilities', ['id' => $window->id]);
});

it('returns JSON when deleting a window via an XHR request', function () {
    $therapist = makeupAvailabilityTherapist();
    $window = ScheduleMakeupAvailability::factory()->create(['therapist_id' => $therapist->id]);

    $this->actingAs($therapist)
        ->deleteJson(route('therapist.makeup-requests.availability.destroy', $window->id))
        ->assertOk()
        ->assertJson(['message' => 'Availability window removed.']);

    $this->assertSoftDeleted('schedule_makeup_availabilities', ['id' => $window->id]);
});

it('forbids a therapist from deleting another therapist window', function () {
    $owner = makeupAvailabilityTherapist();
    $other = User::factory()->therapist()->create();
    $window = ScheduleMakeupAvailability::factory()->create(['therapist_id' => $owner->id]);

    $this->actingAs($other)
        ->delete(route('therapist.makeup-requests.availability.destroy', $window->id))
        ->assertForbidden();

    $this->assertDatabaseHas('schedule_makeup_availabilities', [
        'id' => $window->id,
        'deleted_at' => null,
    ]);
});
