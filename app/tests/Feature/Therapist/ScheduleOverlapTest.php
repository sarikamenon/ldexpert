<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\ServiceStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
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
            'recurrence_type' => 'none',
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_time']);
    }

    public function test_timezone_conversion_stores_as_utc(): void
    {
        // Therapist in EST (UTC-5)
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

        // Schedule at 9:00 AM EST -> 14:00 UTC
        $date = now()->addWeek()->format('Y-m-d');
        $payload = [
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'student_ids' => [$studentUser->id],
            'schedule_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'none',
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'schedule_date' => $date,
            'start_time' => '14:00:00', // 9 AM EST is 14:00 UTC
            'end_time' => '15:00:00',
        ]);
    }
}
