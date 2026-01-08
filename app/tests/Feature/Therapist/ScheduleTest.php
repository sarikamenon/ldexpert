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

    public function test_therapist_can_create_single_schedule_with_valid_ssa(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $studentUser = User::factory()->create(['role' => Role::STUDENT]);
        $studentProfile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
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

        $payload = [
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'student_ids' => [$studentUser->id],
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
            'notes' => 'Test session',
            'location_details' => 'Office A',
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

    public function test_multiple_students_selection_is_disabled(): void
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

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $studentUser1->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $payload = [
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'student_ids' => [$studentUser1->id, $studentUser2->id],
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'none',
            'location_details' => 'Room 101',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_ids']);
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

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.create', ['ssa_id' => $ssa->id]));

        $response->assertStatus(200);
        $response->assertSee('Create New Schedule');
    }

    public function test_therapist_can_submit_schedule_form(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
            'notes' => 'Via form submission',
            'location_details' => 'Office',
        ];

        $response = $this->actingAs($therapist)
            ->post(route('therapist.schedule.store'), $payload);

        $response->assertRedirect(route('therapist.schedule.calendar', ['date' => $payload['schedule_date']]));

        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'service_id' => $service->id,
            // 'schedule_date' => $payload['schedule_date'],
        ]);
    }

    public function test_therapist_can_create_recurring_schedule_with_occurrence_dates(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

        $startDate = now()->addWeek()->format('Y-m-d');
        $endDate = now()->addWeeks(3)->format('Y-m-d');

        // Generate occurrence dates (weekly, excluding weekends)
        $occurrenceDates = [];
        $currentDate = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);

        while ($currentDate <= $endDateTime) {
            $dayOfWeek = (int) $currentDate->format('w');
            // Skip weekends (0 = Sunday, 6 = Saturday)
            if ($dayOfWeek !== 0 && $dayOfWeek !== 6) {
                $occurrenceDates[] = $currentDate->format('Y-m-d');
            }
            $currentDate->modify('+7 days');
        }

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => $endDate,
            'occurrence_dates' => $occurrenceDates,
            'notes' => 'Recurring form schedule',
            'location_details' => 'Recurring Loc',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);

        // Verify parent schedule was created
        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'schedule_date' => $startDate,
            'recurrence_type' => 'weekly',
        ]);

        // Verify occurrences were created (excluding parent)
        $occurrenceCount = Schedule::where('therapist_id', $therapist->id)
            ->where('student_id', $student->id)
            ->where('recurrence_type', 'weekly')
            ->count();

        $this->assertGreaterThan(1, $occurrenceCount);
    }

    public function test_recurring_schedule_requires_occurrence_dates(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addWeeks(2)->format('Y-m-d'),
            // Missing occurrence_dates
            'location_details' => 'Location',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['occurrence_dates']);
    }

    public function test_recurring_schedule_rejects_weekend_dates(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

        // Find next Saturday
        $saturday = new \DateTime;
        while ((int) $saturday->format('w') !== 6) {
            $saturday->modify('+1 day');
        }
        $saturdayStr = $saturday->format('Y-m-d');

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addWeeks(3)->format('Y-m-d'),
            'occurrence_dates' => [
                now()->addWeek()->format('Y-m-d'),
                $saturdayStr, // Weekend date - should be rejected
            ],
            'location_details' => 'Location',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['occurrence_dates']);

        $errors = $response->json('errors.occurrence_dates');
        $this->assertNotEmpty($errors);
        $errorMessage = is_array($errors) ? implode(' ', $errors) : (string) $errors;
        $this->assertTrue(
            str_contains(strtolower($errorMessage), 'weekend') ||
                str_contains(strtolower($errorMessage), 'saturday') ||
                str_contains(strtolower($errorMessage), 'sunday'),
            'Error message should mention weekend/Saturday/Sunday'
        );
    }

    public function test_therapist_can_view_edit_page(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.edit', $schedule->id));

        $response->assertStatus(200);
        $response->assertSee('Edit Schedule');
        $response->assertViewHas('schedule');
    }

    public function test_therapist_cannot_edit_others_schedule(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $otherTherapist = User::factory()->create(['role' => Role::THERAPIST]);
        $schedule = Schedule::factory()->create([
            'therapist_id' => $otherTherapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.edit', $schedule->id));

        $response->assertStatus(404);
    }

    public function test_therapist_can_update_schedule_details(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'notes' => 'Old notes',
        ]);

        $payload = [
            'schedule_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'duration_minutes' => 60,
            'notes' => 'Updated notes',
            'recurrence_type' => 'none',
        ];

        $response = $this->actingAs($therapist)
            ->put(route('therapist.schedule.update', $schedule->id), $payload);

        $response->assertRedirect(route('therapist.schedule.calendar', ['date' => $payload['schedule_date']]));
        $response->assertSessionHas('status', 'Schedule updated successfully.');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'schedule_date' => $payload['schedule_date'],
            'start_time' => $payload['start_time'].':00',
            'notes' => 'Updated notes',
        ]);

        $updatedSchedule = Schedule::find($schedule->id);
        $this->assertEquals($payload['schedule_date'], $updatedSchedule->schedule_date->format('Y-m-d'));
        $this->assertEquals($payload['start_time'], $updatedSchedule->start_time->format('H:i'));
    }

    public function test_therapist_can_delete_schedule(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->deleteJson(route('therapist.schedule.destroy', $schedule->id));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertSoftDeleted('schedules', [
            'id' => $schedule->id,
        ]);
    }
}
