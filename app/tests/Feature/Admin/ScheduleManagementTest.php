<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a fully-wired therapist + active SSA + student + active primary service,
 * which is the minimum graph required for a valid admin schedule create.
 *
 * @return array{therapist: User, student: User, ssa: ServiceSupportAgreement, service: Service}
 */
function adminScheduleGraph(): array
{
    $therapist = User::factory()->therapist()->create();
    TherapistProfile::factory()->create([
        'user_id' => $therapist->id,
        'timezone' => 'America/Chicago',
    ]);

    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    StudentProfile::query()->where('user_id', $student->id)->update(['school_id' => $school->id]);

    $service = Service::factory()->create(['is_direct_service' => false, 'is_group_service' => false]);

    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    return ['therapist' => $therapist, 'student' => $student, 'ssa' => $ssa, 'service' => $service];
}

/**
 * @return array<string, mixed>
 */
function validStorePayload(array $graph): array
{
    return [
        'therapist_id' => $graph['therapist']->id,
        'ssa_id' => $graph['ssa']->id,
        'service_id' => $graph['service']->id,
        'student_ids' => [$graph['student']->id],
        'schedule_date' => CarbonImmutable::now()->addWeek()->toDateString(),
        'start_time' => '09:00',
        'duration_minutes' => 60,
        'recurrence_type' => 'none',
        'location_details' => 'Google Meet link',
    ];
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

it('allows an admin to open the create form with a therapist and SSA selected', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();

    $this->actingAs($admin)
        ->get(route('admin.schedule.create', [
            'therapist_id' => $graph['therapist']->id,
            'ssa_id' => $graph['ssa']->id,
        ]))
        ->assertOk()
        ->assertSee('Create Schedule');
});

it('redirects to the calendar when create is opened without a therapist and SSA', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.schedule.create'))
        ->assertRedirect(route('admin.schedule-calendar.index'));
});

it('forbids a therapist from accessing admin schedule create', function () {
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.schedule.create'))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

it('creates a schedule for the selected therapist', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();

    $this->actingAs($admin)
        ->post(route('admin.schedule.store'), validStorePayload($graph))
        ->assertRedirect(route('admin.schedule-calendar.index'));

    $schedule = Schedule::query()->where('therapist_id', $graph['therapist']->id)->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->student_id)->toBe($graph['student']->id)
        ->and($schedule->status)->toBe(ScheduleStatus::SCHEDULED)
        ->and($schedule->created_by)->toBe($admin->id)
        ->and($schedule->updated_by)->toBe($admin->id);
});

it('requires a therapist_id', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();

    $payload = validStorePayload($graph);
    unset($payload['therapist_id']);

    $this->actingAs($admin)
        ->post(route('admin.schedule.store'), $payload)
        ->assertSessionHasErrors('therapist_id');
});

it('rejects an SSA that does not belong to the selected therapist', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();
    $otherTherapist = User::factory()->therapist()->create();

    $payload = validStorePayload($graph);
    $payload['therapist_id'] = $otherTherapist->id;

    $this->actingAs($admin)
        ->post(route('admin.schedule.store'), $payload)
        ->assertSessionHasErrors('ssa_id');
});

it('rejects an invalid duration', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();

    $payload = validStorePayload($graph);
    $payload['duration_minutes'] = 0;

    $this->actingAs($admin)
        ->post(route('admin.schedule.store'), $payload)
        ->assertSessionHasErrors('duration_minutes');
});

it('rejects duplicate occurrence dates on a recurring schedule', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();

    $date = CarbonImmutable::now()->addWeek()->toDateString();

    $payload = validStorePayload($graph);
    $payload['recurrence_type'] = 'weekly';
    $payload['recurrence_end_date'] = CarbonImmutable::now()->addMonth()->toDateString();
    $payload['occurrence_dates'] = [$date, $date];

    $this->actingAs($admin)
        ->post(route('admin.schedule.store'), $payload)
        ->assertSessionHasErrors('occurrence_dates');
});

it('forbids a therapist from storing a schedule', function () {
    $therapist = User::factory()->therapist()->create();
    $graph = adminScheduleGraph();

    $this->actingAs($therapist)
        ->post(route('admin.schedule.store'), validStorePayload($graph))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Edit / Update
// ---------------------------------------------------------------------------

it('allows an admin to open the edit form', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $graph['therapist']->id,
        'student_id' => $graph['student']->id,
        'ssa_id' => $graph['ssa']->id,
        'service_id' => $graph['service']->id,
        'schedule_date' => CarbonImmutable::now()->addWeek()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.schedule.edit', $schedule->id))
        ->assertOk()
        ->assertSee('Edit Schedule');
});

it('updates a schedule and stamps the admin as updater', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $graph['therapist']->id,
        'student_id' => $graph['student']->id,
        'ssa_id' => $graph['ssa']->id,
        'service_id' => $graph['service']->id,
        'school_id' => $graph['student']->studentProfile->school_id,
        'schedule_date' => CarbonImmutable::now()->addWeek()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'status' => ScheduleStatus::SCHEDULED,
        'billing_status' => BillingStatus::PENDING,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.schedule.update', $schedule->id), [
            'schedule_date' => CarbonImmutable::now()->addWeek()->toDateString(),
            'start_time' => '11:00',
            'duration_minutes' => 45,
            'location_details' => 'Updated location',
        ])
        ->assertRedirect(route('admin.schedule-calendar.index'));

    expect($schedule->fresh()->updated_by)->toBe($admin->id);
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

it('soft-deletes a schedule', function () {
    $admin = User::factory()->admin()->create();
    $graph = adminScheduleGraph();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $graph['therapist']->id,
        'student_id' => $graph['student']->id,
        'billing_status' => BillingStatus::PENDING,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.schedule.destroy', $schedule->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
});
