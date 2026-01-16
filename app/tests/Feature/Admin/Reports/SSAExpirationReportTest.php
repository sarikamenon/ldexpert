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

final class SSAExpirationReportTest extends TestCase
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
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30),
        ], $overrides));
    }

    public function test_admin_can_view_expiration_report(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.expirations.index'));

        $response->assertOk()
            ->assertSee('SSA Expirations & Pipeline Report')
            ->assertViewIs('admin.reports.ssa.expirations')
            ->assertViewHas('upcoming')
            ->assertViewHas('expired')
            ->assertViewHas('pending')
            ->assertViewHas('summary');
    }

    public function test_non_admin_cannot_access_expiration_report(): void
    {
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($therapist)
            ->get(route('admin.reports.ssa.expirations.index'))
            ->assertForbidden();
    }

    public function test_upcoming_expirations_are_shown(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA([
            'end_date' => now()->addDays(15),
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.expirations.index', ['bucket' => 'upcoming']));

        $response->assertOk();
        $upcoming = $response->viewData('upcoming');
        $this->assertGreaterThanOrEqual(1, $upcoming->count());
    }

    public function test_expired_ssas_are_shown(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA([
            'end_date' => now()->subDays(10),
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.expirations.index', ['bucket' => 'expired']));

        $response->assertOk();
        $expired = $response->viewData('expired');
        $this->assertGreaterThanOrEqual(1, $expired->count());
    }

    public function test_export_generates_csv(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.expirations.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('SSA ID', $response->getContent());
    }
}
