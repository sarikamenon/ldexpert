<?php

declare(strict_types=1);

use App\Enums\SessionOutcome;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks session log submission for a schedule excluded from the Past Sessions queue', function (): void {
    $this->withoutMiddleware();

    $therapist = User::factory()->therapist()->create();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    $sessionDate = now();
    $student->studentProfile->update(['school_id' => $school->id]);
    $service = Service::factory()->create(['min_duration_minutes' => 30, 'max_duration_minutes' => 120]);
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
        'start_date' => $sessionDate->clone()->subDay(),
        'end_date' => $sessionDate->clone()->addMonth(),
    ]);
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
        'is_billable' => false,
    ]);

    $startTime = Carbon::parse($sessionDate)->setTime(10, 0, 0);
    $endTime = $startTime->copy()->addMinutes(37);

    $response = $this->actingAs($therapist)
        ->post(route('therapist.session-logs.store'), [
            'schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'session_date' => $sessionDate->format('Y-m-d'),
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'end_time' => $endTime->format('Y-m-d H:i:s'),
            'notes' => str_repeat('a', 20),
            'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
            'is_billable_therapist' => true,
            'is_billable_school' => true,
            '_token' => csrf_token(),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['schedule_id']);
    expect(\App\Models\SessionLog::where('schedule_id', $schedule->id)->exists())->toBeFalse();
});

it('returns 403 when accessing the session log create page for a non-billable schedule', function (): void {
    $therapist = User::factory()->therapist()->create();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    $student->studentProfile->update(['school_id' => $school->id]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
    ]);
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
        'is_billable' => false,
    ]);

    $this->actingAs($therapist)
        ->get(route('therapist.session-logs.create.from-schedule', $schedule))
        ->assertForbidden();
});
