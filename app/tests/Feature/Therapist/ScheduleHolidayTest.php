<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\Role;
use App\Enums\SchoolCalendarEventType;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows schedule creation on a school holiday (warning only, no block)', function () {
    $therapist = User::factory()->create(['role' => Role::THERAPIST]);
    $school = School::factory()->create();
    $studentUser = User::factory()->create(['role' => Role::STUDENT]);
    StudentProfile::factory()->create([
        'user_id' => $studentUser->id,
        'school_id' => $school->id,
    ]);

    $therapist->students()->attach($studentUser->id, [
        'assigned_at' => now(),
        'status' => 'active',
    ]);

    $service = Service::factory()->create([
        'is_group_service' => false,
    ]);

    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $studentUser->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $holidayDate = now()->next(Carbon::MONDAY)->format('Y-m-d');
    SchoolCalendarEvent::factory()->holiday()->create([
        'school_id' => $school->id,
        'start_date' => $holidayDate,
        'end_date' => $holidayDate,
        'event_type' => SchoolCalendarEventType::HOLIDAY->value,
    ]);

    $payload = [
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'student_ids' => [$studentUser->id],
        'schedule_date' => $holidayDate,
        'start_time' => '10:00',
        'end_time' => '10:37',
        'duration_minutes' => 37,
        'recurrence_type' => 'none',
        'notes' => 'Test session',
        'location_details' => 'Office A',
    ];

    test()->actingAs($therapist)
        ->postJson(route('therapist.schedule.store'), $payload)
        ->assertCreated()
        ->assertJsonMissingValidationErrors(['schedule_date']);

    expect(Schedule::query()
        ->where('therapist_id', $therapist->id)
        ->where('student_id', $studentUser->id)
        ->whereDate('schedule_date', $holidayDate)
        ->exists())->toBeTrue();
});

it('allows schedule update on a school holiday (warning only, no block)', function () {
    $therapist = User::factory()->create(['role' => Role::THERAPIST]);
    $school = School::factory()->create();
    $studentUser = User::factory()->create(['role' => Role::STUDENT]);
    StudentProfile::factory()->create([
        'user_id' => $studentUser->id,
        'school_id' => $school->id,
    ]);

    $therapist->students()->attach($studentUser->id, [
        'assigned_at' => now(),
        'status' => 'active',
    ]);

    $service = Service::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $studentUser->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
        'schedule_date' => now()->addDays(1)->format('Y-m-d'),
    ]);

    $holidayDate = now()->next(Carbon::TUESDAY)->format('Y-m-d');
    SchoolCalendarEvent::factory()->holiday()->create([
        'school_id' => $school->id,
        'start_date' => $holidayDate,
        'end_date' => $holidayDate,
    ]);

    $payload = [
        'schedule_date' => $holidayDate,
        'start_time' => '09:00',
        'duration_minutes' => 60,
    ];

    test()->actingAs($therapist)
        ->putJson(route('therapist.schedule.update', $schedule->id), $payload)
        ->assertOk()
        ->assertJsonMissingValidationErrors(['schedule_date']);

    expect($schedule->fresh()->schedule_date->toDateString())->toBe($holidayDate);
});
