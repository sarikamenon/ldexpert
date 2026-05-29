<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build an owning therapist + a REQUESTED makeup request whose original
 * schedule still exists, wired so the book policy passes.
 *
 * @return array{0: User, 1: ScheduleMakeupRequest, 2: Schedule}
 */
function makeupBookingFixture(): array
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::factory()->create(['user_id' => $student->id]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
    ]);

    $request = ScheduleMakeupRequest::factory()->requested()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'makeup_schedule_id' => null,
    ]);

    return [$therapist, $request, $schedule];
}

it('book redirects to the edit flow when the original schedule still exists', function () {
    [$therapist, $request, $schedule] = makeupBookingFixture();

    $response = $this->actingAs($therapist)
        ->get(route('therapist.makeup-requests.book', $request->id));

    $response->assertRedirect(route('therapist.schedule.edit', [
        'id' => $schedule->id,
        'makeup_request_id' => $request->id,
    ]));
});

it('book falls back to creating a schedule when the original was soft-deleted', function () {
    [$therapist, $request, $schedule] = makeupBookingFixture();

    // Soft-delete the original — the live relation goes null but ssa_id must
    // still be recoverable via withTrashed() for the create fallback.
    $ssaId = $schedule->ssa_id;
    $schedule->delete();

    $response = $this->actingAs($therapist)
        ->get(route('therapist.makeup-requests.book', $request->id));

    $response->assertRedirect(route('therapist.schedule.create', [
        'ssa_id' => $ssaId,
        'date' => $request->event_date->toDateString(),
        'makeup_request_id' => $request->id,
    ]));
});

it('book is forbidden for a therapist who does not own the request', function () {
    [, $request] = makeupBookingFixture();
    $other = User::factory()->therapist()->create();

    $this->actingAs($other)
        ->get(route('therapist.makeup-requests.book', $request->id))
        ->assertForbidden();
});

it('updating a schedule with makeup_request_id links it and flips status to scheduled', function () {
    $therapist = User::factory()->therapist()->create(['timezone' => 'America/New_York']);
    TherapistProfile::factory()->create([
        'user_id' => $therapist->id,
        'timezone' => 'America/New_York',
    ]);
    $student = User::factory()->student()->create();
    StudentProfile::factory()->create(['user_id' => $student->id]);
    $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ]);
    $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
    ]);

    $request = ScheduleMakeupRequest::factory()->requested()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'makeup_schedule_id' => null,
    ]);

    $payload = [
        'ssa_id' => $ssa->id,
        'student_ids' => [$student->id],
        'service_id' => $service->id,
        'schedule_date' => now()->addWeek()->format('Y-m-d'),
        'start_time' => '09:00',
        'duration_minutes' => 60,
        'location_details' => 'Makeup Loc',
        'makeup_request_id' => $request->id,
    ];

    $this->actingAs($therapist)
        ->putJson(route('therapist.schedule.update', $schedule->id), $payload)
        ->assertOk();

    $request->refresh();
    expect($request->status)->toBe(ScheduleMakeupRequestStatus::SCHEDULED)
        ->and($request->makeup_schedule_id)->not->toBeNull();
});

it('rejects a non-integer makeup_request_id on schedule update', function () {
    $therapist = User::factory()->therapist()->create(['timezone' => 'America/New_York']);
    TherapistProfile::factory()->create([
        'user_id' => $therapist->id,
        'timezone' => 'America/New_York',
    ]);
    $student = User::factory()->student()->create();
    StudentProfile::factory()->create(['user_id' => $student->id]);
    $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ]);
    $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
    ]);

    $payload = [
        'ssa_id' => $ssa->id,
        'student_ids' => [$student->id],
        'service_id' => $service->id,
        'schedule_date' => now()->addWeek()->format('Y-m-d'),
        'start_time' => '09:00',
        'duration_minutes' => 60,
        'makeup_request_id' => 'not-an-int',
    ];

    $this->actingAs($therapist)
        ->putJson(route('therapist.schedule.update', $schedule->id), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['makeup_request_id']);
});
