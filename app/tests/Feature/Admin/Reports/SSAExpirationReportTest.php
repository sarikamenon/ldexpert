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

final class SSAExpirationReportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @param array<string, mixed> $overrides */
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
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30),
        ], $overrides));
    }

    public function test_admin_can_view_expiration_report(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.expirations.index'));

        $response->assertOk()
            ->assertSee('SSA Expirations & Pipeline Report')
            ->assertViewIs('admin.reports.ssa.expirations')
            ->assertViewHas('datatableUrl')
            ->assertViewHas('schools')
            ->assertViewHas('therapists')
            ->assertViewHas('services');
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

    public function test_data_endpoint_returns_upcoming(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA([
            'end_date' => now()->addDays(15),
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.reports.ssa.expirations.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'filter_bucket' => 'upcoming',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
                'summary',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('recordsTotal'));
    }

    public function test_data_endpoint_returns_expired(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA([
            'end_date' => now()->subDays(10),
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.reports.ssa.expirations.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'filter_bucket' => 'expired',
            ]);

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('recordsTotal'));
    }

    public function test_export_generates_csv(): void
    {
        $admin = $this->createAdmin();
        $this->createSSA();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.ssa.expirations.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('SSA ID', $response->streamedContent());
    }
}
