<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Reports;

use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SSAUtilizationReportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createSSA(array $overrides = []): ServiceSupportAgreement
    {
        $student = User::factory()->create([
            'role' => Role::STUDENT,
            'status' => UserStatus::ACTIVE,
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
            'tho_minutes' => 1000,
            'served_minutes' => 800,
        ], $overrides));
    }

    public function test_admin_can_view_utilization_report(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.utilization.index'));

        $response->assertOk()
            ->assertSee('SSA Utilization & Compliance Report')
            ->assertViewIs('admin.reports.ssa.utilization')
            ->assertViewHas('ssas')
            ->assertViewHas('summary')
            ->assertViewHas('schools')
            ->assertViewHas('therapists')
            ->assertViewHas('services');
    }

    public function test_non_admin_cannot_access_utilization_report(): void
    {
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($therapist)
            ->get(route('admin.reports.ssa.utilization.index'))
            ->assertForbidden();
    }

    public function test_filters_work_correctly(): void
    {
        $admin = $this->createAdmin();
        $ssa1 = $this->createSSA(['tho_minutes' => 1000, 'served_minutes' => 500]);
        $ssa2 = $this->createSSA(['tho_minutes' => 2000, 'served_minutes' => 2000]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.utilization.index', [
                'start_date' => now()->subDays(30)->format('Y-m-d'),
                'end_date' => now()->addDays(30)->format('Y-m-d'),
            ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->viewData('ssas')->total());
    }

    public function test_utilization_percentage_calculates_correctly(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA(['tho_minutes' => 1000, 'served_minutes' => 800]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.utilization.index'));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(80.0, $summary['overall_utilization_percent']);
    }

    public function test_export_generates_csv(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.utilization.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('SSA ID', $response->getContent());
        $this->assertStringContainsString('Student Name', $response->getContent());
    }
}
