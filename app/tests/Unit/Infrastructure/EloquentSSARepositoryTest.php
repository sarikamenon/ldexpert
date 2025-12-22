<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Repositories\EloquentSSARepository;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentSSARepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentSSARepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentSSARepository;
    }

    public function test_find_with_relations_loads_specified_relations(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $therapist = User::factory()->create(['role' => 'therapist']);
        $service = Service::factory()->create();
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
        ]);

        $found = $this->repository->findWithRelations($ssa->id, ['student', 'primaryService', 'assignedTherapist']);

        $this->assertInstanceOf(ServiceSupportAgreement::class, $found);
        $this->assertTrue($found->relationLoaded('student'));
        $this->assertTrue($found->relationLoaded('primaryService'));
        $this->assertTrue($found->relationLoaded('assignedTherapist'));
    }

    public function test_find_with_relations_returns_null_when_not_found(): void
    {
        $found = $this->repository->findWithRelations(999999, ['student']);

        $this->assertNull($found);
    }

    public function test_find_with_relations_works_with_empty_relations(): void
    {
        $ssa = ServiceSupportAgreement::factory()->create();

        $found = $this->repository->findWithRelations($ssa->id, []);

        $this->assertInstanceOf(ServiceSupportAgreement::class, $found);
        $this->assertSame($ssa->id, $found->id);
    }

    public function test_get_assigned_ssas_for_therapist_returns_all_ssas(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);
        $student1 = User::factory()->create(['role' => 'student']);
        $student2 = User::factory()->create(['role' => 'student']);
        $service = Service::factory()->create();

        $ssa1 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student1->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
        ]);

        $ssa2 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student2->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
        ]);

        // Create an SSA assigned to a different therapist
        $otherTherapist = User::factory()->create(['role' => 'therapist']);
        ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => $otherTherapist->id,
        ]);

        $result = $this->repository->getAssignedSSAsForTherapist($therapist->id);

        $this->assertCount(2, $result);
        $this->assertTrue($result->contains('id', $ssa1->id));
        $this->assertTrue($result->contains('id', $ssa2->id));
    }

    public function test_get_assigned_ssas_for_therapist_returns_empty_collection_when_none_assigned(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);

        $result = $this->repository->getAssignedSSAsForTherapist($therapist->id);

        $this->assertCount(0, $result);
    }

    public function test_get_ssas_for_therapist_dashboard_returns_limited_results(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);
        $school = School::factory()->create();
        $service = Service::factory()->create();

        // Create 10 SSAs for this therapist
        for ($i = 0; $i < 10; $i++) {
            $student = User::factory()->create(['role' => 'student']);
            StudentProfile::factory()->create([
                'user_id' => $student->id,
                'school_id' => $school->id,
            ]);

            ServiceSupportAgreement::factory()->create([
                'student_id' => $student->id,
                'primary_service_id' => $service->id,
                'assigned_therapist_id' => $therapist->id,
                'created_at' => Carbon::now()->subDays($i),
            ]);
        }

        $result = $this->repository->getSSAsForTherapistDashboard($therapist->id, 5);

        $this->assertCount(5, $result);
        $this->assertTrue($result->first()->relationLoaded('student'));
        $this->assertTrue($result->first()->relationLoaded('student.studentProfile.school'));
        $this->assertTrue($result->first()->relationLoaded('primaryService'));
    }

    public function test_get_ssas_for_therapist_dashboard_orders_by_created_at_desc(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);
        $school = School::factory()->create();
        $service = Service::factory()->create();

        $student1 = User::factory()->create(['role' => 'student']);
        StudentProfile::factory()->create([
            'user_id' => $student1->id,
            'school_id' => $school->id,
        ]);

        $student2 = User::factory()->create(['role' => 'student']);
        StudentProfile::factory()->create([
            'user_id' => $student2->id,
            'school_id' => $school->id,
        ]);

        $ssa1 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student1->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $ssa2 = ServiceSupportAgreement::factory()->create([
            'student_id' => $student2->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->repository->getSSAsForTherapistDashboard($therapist->id, 5);

        $this->assertSame($ssa2->id, $result->first()->id);
        $this->assertSame($ssa1->id, $result->last()->id);
    }

    public function test_count_new_students_this_month_returns_correct_count(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);
        $service = Service::factory()->create();

        // Create 3 SSAs with different students this month
        for ($i = 0; $i < 3; $i++) {
            $student = User::factory()->create(['role' => 'student']);
            ServiceSupportAgreement::factory()->create([
                'student_id' => $student->id,
                'primary_service_id' => $service->id,
                'assigned_therapist_id' => $therapist->id,
                'created_at' => Carbon::now()->subDays($i),
            ]);
        }

        // Create an SSA from last month (should not be counted)
        $oldStudent = User::factory()->create(['role' => 'student']);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $oldStudent->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        // Create an SSA for a different therapist (should not be counted)
        $otherTherapist = User::factory()->create(['role' => 'therapist']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $otherStudent->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $otherTherapist->id,
            'created_at' => Carbon::now(),
        ]);

        $result = $this->repository->countNewStudentsThisMonth($therapist->id);

        $this->assertSame(3, $result);
    }

    public function test_count_new_students_this_month_returns_zero_when_none_this_month(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);
        $service = Service::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $result = $this->repository->countNewStudentsThisMonth($therapist->id);

        $this->assertSame(0, $result);
    }

    public function test_count_new_students_this_month_counts_distinct_students(): void
    {
        $therapist = User::factory()->create(['role' => 'therapist']);
        $service = Service::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        // Create multiple SSAs for the same student this month
        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'created_at' => Carbon::now(),
        ]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->repository->countNewStudentsThisMonth($therapist->id);

        // Should count distinct students, so should be 1, not 2
        $this->assertSame(1, $result);
    }
}
