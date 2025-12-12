<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_create_session_log_from_schedule(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        $service = Service::factory()->create();
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'schedule_id' => $schedule->id,
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 50), // Minimum 50 characters
                'is_billable_therapist' => true,
                'is_billable_school' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('session_logs', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'schedule_id' => $schedule->id,
            'status' => SessionLogStatus::DRAFT->value,
        ]);
    }

    public function test_therapist_can_create_standalone_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        $service = Service::factory()->create();
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 50),
                'is_billable_therapist' => true,
                'is_billable_school' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('session_logs', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'schedule_id' => null,
        ]);
    }

    public function test_session_log_requires_minimum_notes_length(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        $service = Service::factory()->create();
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => 'Short', // Less than 50 characters
                'is_billable_therapist' => true,
            ]);

        $response->assertSessionHasErrors(['notes']);
    }
}
