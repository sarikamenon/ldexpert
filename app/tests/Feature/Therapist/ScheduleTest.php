<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\BillingStatus;
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

final class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_view_schedule_calendar(): void
    {
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.calendar'));

        $response->assertStatus(200);
        $response->assertViewIs('therapist.schedule.calendar');
    }

    public function test_non_therapist_cannot_view_schedule_calendar(): void
    {
        $admin = User::factory()->create([
            'role' => Role::ADMIN,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('therapist.schedule.calendar'));

        $response->assertForbidden();
    }

    public function test_therapist_can_get_schedules_via_ajax(): void
    {
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
        ]);

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.schedule.schedules', [
                'date' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'schedules',
        ]);
    }

    public function test_schedule_filters_validation(): void
    {
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
        ]);

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.schedule.schedules', [
                'date' => 'invalid-date',
            ]));

        $response->assertStatus(422);
    }

    public function test_therapist_can_create_single_schedule_without_ssa(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        $studentProfile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
        ]);

        // Attach therapist to student
        $therapist->students()->attach($studentUser->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $service = Service::factory()->create([
            'is_group_service' => false,
        ]);

        $payload = [
            'service_id' => $service->id,
            'student_ids' => [$studentUser->id],
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'none',
            'notes' => 'Test session',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser->id,
            'service_id' => $service->id,
            'schedule_date' => $payload['schedule_date'],
        ]);
    }

    public function test_therapist_can_create_group_schedule_with_multiple_students(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $studentUser1 = User::factory()->create(['role' => Role::STUDENT]);
        $studentUser2 = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser1->id]);
        StudentProfile::factory()->create(['user_id' => $studentUser2->id]);

        $therapist->students()->attach([$studentUser1->id, $studentUser2->id], [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $service = Service::factory()->create([
            'is_group_service' => true,
        ]);

        $payload = [
            'service_id' => $service->id,
            'student_ids' => [$studentUser1->id, $studentUser2->id],
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'none',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseCount('schedules', 2);

        $firstSchedule = Schedule::query()->first();
        $this->assertDatabaseMissing('schedules', [
            'id' => $firstSchedule->id,
            'group_batch_number' => null,
        ]);
        $groupBatch = $firstSchedule->group_batch_number;

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser1->id,
            'group_batch_number' => $groupBatch,
        ]);

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser2->id,
            'group_batch_number' => $groupBatch,
        ]);
    }

    public function test_group_schedule_requires_students_to_share_service_via_ssa(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $studentUser1 = User::factory()->create(['role' => Role::STUDENT]);
        $studentUser2 = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser1->id]);
        StudentProfile::factory()->create(['user_id' => $studentUser2->id]);

        $therapist->students()->attach([$studentUser1->id, $studentUser2->id], [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $service = Service::factory()->create([
            'is_group_service' => true,
        ]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser1->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $payload = [
            'service_id' => $service->id,
            'student_ids' => [$studentUser1->id, $studentUser2->id],
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'none',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_group_schedule_allows_shared_additional_services(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $studentUser1 = User::factory()->create(['role' => Role::STUDENT]);
        $studentUser2 = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser1->id]);
        StudentProfile::factory()->create(['user_id' => $studentUser2->id]);

        $therapist->students()->attach([$studentUser1->id, $studentUser2->id], [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $primaryServiceOne = Service::factory()->create();
        $primaryServiceTwo = Service::factory()->create();
        $indirectService = Service::factory()->create([
            'is_group_service' => true,
            'is_direct_service' => false,
        ]);

        $ssaOne = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser1->id,
            'primary_service_id' => $primaryServiceOne->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);
        $ssaOne->additionalServices()->sync([$indirectService->id]);

        $ssaTwo = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser2->id,
            'primary_service_id' => $primaryServiceTwo->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);
        $ssaTwo->additionalServices()->sync([$indirectService->id]);

        $payload = [
            'service_id' => $indirectService->id,
            'student_ids' => [$studentUser1->id, $studentUser2->id],
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'none',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser1->id,
            'service_id' => $indirectService->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser2->id,
            'service_id' => $indirectService->id,
        ]);
    }

    public function test_therapist_can_update_schedule_billing_status(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);

        $therapist->students()->attach($studentUser->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $service = Service::factory()->create();

        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser->id,
            'service_id' => $service->id,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->putJson(route('therapist.schedule.update-billing-status', $schedule->id), [
                'billing_status' => BillingStatus::BILLED->value,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'billing_status' => BillingStatus::BILLED->value,
        ]);
    }

    public function test_therapist_can_view_schedule_create_page(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.create'));

        $response->assertStatus(200);
        $response->assertSee('Create New Schedule');
    }

    public function test_therapist_can_submit_schedule_form(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $payload = [
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'none',
            'notes' => 'Via form submission',
        ];

        $response = $this->actingAs($therapist)
            ->post(route('therapist.schedule.store'), $payload);

        $response->assertRedirect(route('therapist.schedule.calendar', ['date' => $payload['schedule_date']]));

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'service_id' => $service->id,
            'schedule_date' => $payload['schedule_date'],
        ]);
    }

    public function test_therapist_can_create_recurring_schedule_with_occurrence_count(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $startDate = now()->addWeek()->format('Y-m-d');

        $payload = [
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'weekly',
            'occurrence_count' => 3,
            'notes' => 'Recurring form schedule',
        ];

        $response = $this->actingAs($therapist)
            ->post(route('therapist.schedule.store'), $payload);

        $response->assertRedirect(route('therapist.schedule.calendar', ['date' => $payload['schedule_date']]));

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'recurrence_type' => 'weekly',
        ]);

        $this->assertDatabaseCount('schedules', 3);
    }
}
