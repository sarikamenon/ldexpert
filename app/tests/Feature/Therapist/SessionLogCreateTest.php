<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\BillingStatus;
use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Enums\SessionLogStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_therapist_can_create_session_log_from_schedule(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
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
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        $sessionDate = now()->format('Y-m-d');
        $startTime = now()->setTime(10, 0, 0);
        $endTime = $startTime->copy()->addHour();

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'schedule_id' => $schedule->id,
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => $sessionDate,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 50), // Minimum 50 characters
                'is_billable_therapist' => true,
                'is_billable_school' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
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
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        $sessionDate = now()->format('Y-m-d');
        $startTime = now()->setTime(10, 0, 0);
        $endTime = $startTime->copy()->addHour();

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'school_id' => $school->id,
                'session_date' => $sessionDate,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 50),
                'is_billable_therapist' => true,
                'is_billable_school' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('session_logs', [
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'schedule_id' => null,
        ]);
    }

    public function test_student_is_inferred_from_ssa_when_not_provided(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        $sessionDate = now()->format('Y-m-d');
        $startTime = now()->setTime(10, 0, 0);
        $endTime = $startTime->copy()->addHour();

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                // intentionally omit student_id to ensure it is inferred from SSA
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'school_id' => $school->id,
                'session_date' => $sessionDate,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 50),
                'is_billable_therapist' => true,
                'is_billable_school' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

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
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['notes']);
    }

    public function test_rejects_session_outside_ssa_dates(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create();
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['session_date']);
    }

    public function test_rejects_duration_below_service_minimum(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 120,
        ]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addMinutes(30)->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['duration_minutes']);
    }

    public function test_rejects_session_log_for_billed_schedule(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
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
            'billing_status' => BillingStatus::BILLED,
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'schedule_id' => $schedule->id,
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_marks_schedule_as_billed_after_log_creation(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
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
            'billing_status' => BillingStatus::PENDING,
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'schedule_id' => $schedule->id,
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $this->assertSame(BillingStatus::BILLED, $schedule->fresh()->billing_status);
    }

    public function test_rejects_when_contracts_missing(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => now()->format('Y-m-d'),
                'start_time' => now()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_rejects_when_service_rates_missing(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $service = Service::factory()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $sessionDate = now();

        // Create active contracts but WITHOUT service rates
        $therapistProfile = $therapist->therapistProfile
            ?? TherapistProfile::factory()->create([
                'user_id' => $therapist->id,
            ]);

        TherapistContract::create([
            'therapist_id' => $therapistProfile->id,
            'start_date' => $sessionDate->copy()->subDay()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => $sessionDate->copy()->subDay()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => $sessionDate->format('Y-m-d'),
                'start_time' => $sessionDate->format('Y-m-d H:i:s'),
                'end_time' => $sessionDate->copy()->addHour()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_rejects_multiple_logs_for_same_schedule(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
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
            'billing_status' => BillingStatus::PENDING,
        ]);

        $this->seedContracts($therapist, $school, $service, now());

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'schedule_id' => $schedule->id,
            'school_id' => $school->id,
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
                'notes' => str_repeat('a', 60),
                'is_billable_therapist' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors(['error']);
    }

    private function seedContracts(User $therapist, School $school, Service $service, Carbon $sessionDate): void
    {
        // Reuse existing therapist profile if it exists to keep contracts aligned
        $therapistProfile = $therapist->therapistProfile
            ?? TherapistProfile::factory()->create([
                'user_id' => $therapist->id,
            ]);

        $therapistContract = TherapistContract::create([
            'therapist_id' => $therapistProfile->id,
            'start_date' => $sessionDate->copy()->subDay()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        TherapistContractService::create([
            'therapist_contract_id' => $therapistContract->id,
            'service_id' => $service->id,
            'rate' => 100,
            'rate_type' => RateType::HOURLY->value,
        ]);

        $schoolContract = SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => $sessionDate->copy()->subDay()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        SchoolContractService::create([
            'school_contract_id' => $schoolContract->id,
            'service_id' => $service->id,
            'rate' => 150,
            'rate_type' => RateType::HOURLY->value,
        ]);
    }
}
