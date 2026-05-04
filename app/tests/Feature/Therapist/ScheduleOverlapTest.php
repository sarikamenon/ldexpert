<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleOverlapTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_schedule_fails_if_overlapping_existing_schedule_for_therapist(): void
    {
        // 1. Setup Therapist
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);

        // 2. Setup Existing Schedule (9:00 - 10:00)
        Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        // 3. Setup Student & Service for new schedule
        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($studentUser->id, ['assigned_at' => now(), 'status' => 'active']);

        // 4. Attempt to create overlapping schedule (09:30 - 10:30)
        $payload = [
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'student_ids' => [$studentUser->id],
            'schedule_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:30',
            'end_time' => '10:30',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_time']);
    }

    public function test_create_schedule_fails_if_overlapping_existing_schedule_for_student(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
        $otherTherapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);

        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);

        // Existing schedule with Other Therapist (9:00 - 10:00)
        Schedule::factory()->create([
            'therapist_id' => $otherTherapist->id,
            'student_id' => $studentUser->id,
            'schedule_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($studentUser->id, ['assigned_at' => now(), 'status' => 'active']);

        // Attempt to create overlapping schedule (09:30 - 10:30)
        $payload = [
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'student_ids' => [$studentUser->id],
            'schedule_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:30',
            'end_time' => '10:30',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_time']);
    }

    public function test_update_recurring_schedule_does_not_overlap_with_own_siblings(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($studentUser->id, ['assigned_at' => now(), 'status' => 'active']);

        $batchNumber = 'REC-SIBLING-TEST';
        $date = now()->addWeek()->format('Y-m-d');
        $siblingDate = now()->addWeeks(2)->format('Y-m-d');

        // Parent schedule (the one we will edit)
        $parent = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser->id,
            'service_id' => $service->id,
            'ssa_id' => $ssa->id,
            'schedule_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addMonths(2)->format('Y-m-d'),
            'recurring_batch_number' => $batchNumber,
            'parent_schedule_id' => null,
        ]);

        // Sibling occurrence at the same time next week — must NOT block the edit
        Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser->id,
            'service_id' => $service->id,
            'ssa_id' => $ssa->id,
            'schedule_date' => $siblingDate,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'recurrence_type' => 'weekly',
            'recurring_batch_number' => $batchNumber,
            'parent_schedule_id' => $parent->id,
        ]);

        // Edit the parent: change recurrence to bi-weekly, same time
        $payload = [
            'service_id' => $service->id,
            'duration_minutes' => 30,
            'schedule_date' => $date,
            'start_time' => '09:00',
            'recurrence_type' => 'bi_weekly',
            'recurrence_end_date' => now()->addMonths(2)->format('Y-m-d'),
            'location_details' => 'Test location',
        ];

        $response = $this->actingAs($therapist)
            ->putJson(route('therapist.schedule.update', $parent->id), $payload);

        $response->assertStatus(200);
    }

    public function test_update_recurring_schedule_still_detects_overlap_from_different_series(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($studentUser->id, ['assigned_at' => now(), 'status' => 'active']);

        $date = now()->addWeek()->format('Y-m-d');

        // A completely separate (different batch) schedule at 09:00 on the same date
        Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'recurrence_type' => 'none',
            'recurring_batch_number' => null,
            'parent_schedule_id' => null,
        ]);

        // The schedule being edited (different batch)
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser->id,
            'service_id' => $service->id,
            'ssa_id' => $ssa->id,
            'schedule_date' => now()->addWeeks(3)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'recurrence_type' => 'weekly',
            'recurring_batch_number' => 'REC-OTHER',
            'parent_schedule_id' => null,
            'recurrence_end_date' => now()->addMonths(2)->format('Y-m-d'),
        ]);

        // Try to move it to 09:00 on $date — should conflict with the separate schedule
        $payload = [
            'service_id' => $service->id,
            'duration_minutes' => 30,
            'schedule_date' => $date,
            'start_time' => '09:00',
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addMonths(2)->format('Y-m-d'),
            'location_details' => 'Test location',
        ];

        $response = $this->actingAs($therapist)
            ->putJson(route('therapist.schedule.update', $schedule->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_time']);
    }

    public function test_timezone_conversion_stores_as_utc(): void
    {
        // Therapist in Eastern time (America/New_York, DST-aware)
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'America/New_York']);

        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);
        $therapist->students()->attach($studentUser->id, ['assigned_at' => now(), 'status' => 'active']);

        // Schedule at 9:00 AM local -> stored as UTC
        $date = now()->addWeek()->format('Y-m-d');
        $payload = [
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'student_ids' => [$studentUser->id],
            'schedule_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'schedule_date' => $date,
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
        ]);
    }
}
