<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\BillingStatus;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SchedulePendingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_schedule_page_shows_table_with_expected_columns(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending'));

        $response->assertStatus(200);
        $response->assertViewIs('therapist.schedule.pending');

        // Updated column names - grouped columns
        $response->assertSeeText('Date & Time');
        $response->assertSeeText('Student, Service & School');
        $response->assertSeeText('Action');
    }

    public function test_pending_schedule_page_lists_billable_schedules_with_actions(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
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
            'schedule_date' => now()->subDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:45',
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending'));

        $response->assertStatus(200);
        $response->assertSee($student->name);
        $response->assertSee('Bill Your Session');
        $response->assertSee('Delete Schedule');
        $response->assertSee(route('therapist.session-logs.create.from-schedule', $schedule->id), false);
    }

    public function test_pending_schedule_page_excludes_non_billable_schedules(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $school = School::factory()->create(['non_billable_scheduling' => true]);
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);

        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'school_id' => $school->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
            'is_billable' => false,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending'));

        $response->assertStatus(200);
        $viewData = $response->viewData('pendingSchedules');
        $this->assertCount(0, $viewData);
    }

    public function test_pending_schedule_page_shows_filter_form(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending'));

        $response->assertStatus(200);
        $response->assertSee('Student');
        $response->assertSee('SSA');
        $response->assertSee('Service');
        $response->assertSee('From Date');
        $response->assertSee('To Date');
        $response->assertSee('Filter');
        $response->assertSee('All Students');
        $response->assertSee('All SSAs');
        $response->assertSee('All Services');
    }

    public function test_pending_schedule_page_filters_by_student(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student1 = User::factory()->create(['role' => Role::STUDENT, 'name' => 'Student One']);
        $student2 = User::factory()->create(['role' => Role::STUDENT, 'name' => 'Student Two']);

        StudentProfile::factory()->create(['user_id' => $student1->id]);
        StudentProfile::factory()->create(['user_id' => $student2->id]);

        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa1 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student1->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);
        $ssa2 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student2->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $schedule1 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student1->id,
            'ssa_id' => $ssa1->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $schedule2 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student2->id,
            'ssa_id' => $ssa2->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending', ['student_id' => $student1->id]));

        $response->assertStatus(200);
        $response->assertSee('Student One');
        $viewData = $response->viewData('pendingSchedules');
        $this->assertCount(1, $viewData);
        $this->assertEquals($student1->id, $viewData->first()->student_id);
    }

    public function test_pending_schedule_page_filters_by_ssa(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa1 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);
        $ssa2 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $schedule1 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa1->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $schedule2 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa2->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending', ['ssa_id' => $ssa1->id]));

        $response->assertStatus(200);
        $viewData = $response->viewData('pendingSchedules');
        $this->assertCount(1, $viewData);
        $this->assertEquals($ssa1->id, $viewData->first()->ssa_id);
    }

    public function test_pending_schedule_page_filters_by_service(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $service1 = Service::factory()->create(['status' => ServiceStatus::ACTIVE, 'name' => 'Service One']);
        $service2 = Service::factory()->create(['status' => ServiceStatus::ACTIVE, 'name' => 'Service Two']);

        $ssa1 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service1->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);
        $ssa2 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service2->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $schedule1 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa1->id,
            'service_id' => $service1->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $schedule2 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa2->id,
            'service_id' => $service2->id,
            'schedule_date' => now()->subDay()->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending', ['service_id' => $service1->id]));

        $response->assertStatus(200);
        $viewData = $response->viewData('pendingSchedules');
        $this->assertCount(1, $viewData);
        $this->assertEquals($service1->id, $viewData->first()->service_id);
    }

    public function test_pending_schedule_page_filters_by_date_range(): void
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

        $schedule1 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDays(5)->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $schedule2 = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'schedule_date' => now()->subDays(10)->toDateString(),
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
        ]);

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->subDays(3)->format('Y-m-d');

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.pending', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]));

        $response->assertStatus(200);
        $viewData = $response->viewData('pendingSchedules');
        $this->assertCount(1, $viewData);
        $this->assertEquals($schedule1->id, $viewData->first()->id);
    }

    public function test_pending_schedule_page_provides_reference_data_for_filters(): void
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
            ->get(route('therapist.schedule.pending'));

        $response->assertStatus(200);

        $students = $response->viewData('students');
        $ssas = $response->viewData('ssas');
        $services = $response->viewData('services');
        $filters = $response->viewData('filters');

        $this->assertNotNull($filters);
        $this->assertTrue($students->pluck('id')->contains($student->id));
        $this->assertTrue($ssas->pluck('id')->contains($ssa->id));
        $this->assertTrue($services->pluck('id')->contains($service->id));
    }

    public function test_pending_schedule_page_shows_clear_button_when_filters_are_applied(): void
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
            ->get(route('therapist.schedule.pending', ['student_id' => $student->id]));

        $response->assertStatus(200);
        $response->assertSee('Clear');
        $response->assertSee(route('therapist.schedule.pending'), false);
    }
}
