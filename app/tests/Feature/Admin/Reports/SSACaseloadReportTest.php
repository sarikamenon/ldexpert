<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Reports;

use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SSACaseloadReportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createSSA(array $overrides = []): ServiceSupportAgreement
    {
        $school = School::factory()->create();
        $student = User::factory()->create([
            'role' => Role::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);

        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);

        $service = Service::factory()->create([
            'status' => 'active',
        ]);

        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'status' => UserStatus::ACTIVE,
        ]);

        return ServiceSupportAgreement::factory()->create(array_merge([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ], $overrides));
    }

    public function test_admin_can_view_caseload_report(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.caseload.index'));

        $response->assertOk()
            ->assertSee('SSA Caseload & Assignment Report')
            ->assertViewIs('admin.reports.ssa.caseload')
            ->assertViewHas('therapistData')
            ->assertViewHas('unassignedSSAs')
            ->assertViewHas('summary');
    }

    public function test_non_admin_cannot_access_caseload_report(): void
    {
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($therapist)
            ->get(route('admin.reports.ssa.caseload.index'))
            ->assertForbidden();
    }

    public function test_filters_work_correctly(): void
    {
        $admin = $this->createAdmin();
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->createSSA(['assigned_therapist_id' => $therapist->id]);
        $this->createSSA(['assigned_therapist_id' => null, 'status' => SSAStatus::PENDING]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.caseload.index', [
                'therapist_ids' => [$therapist->id],
            ]));

        $response->assertOk();
    }

    public function test_export_generates_csv(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.caseload.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Therapist Name', $response->getContent());
    }
}
