<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Services\DashboardService;
use Mockery;
use Tests\TestCase;

final class DashboardServiceTest extends TestCase
{
    public function test_get_key_metrics_returns_correct_structure(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getSchoolCount')->once()->andReturn(10);
        $repository->shouldReceive('getActiveSchoolCount')->once()->andReturn(8);
        $repository->shouldReceive('getInactiveSchoolCount')->once()->andReturn(2);
        $repository->shouldReceive('getNewSchoolsThisMonth')->once()->andReturn(1);
        $repository->shouldReceive('getTherapistCount')->once()->andReturn(15);
        $repository->shouldReceive('getActiveTherapistCount')->once()->andReturn(12);
        $repository->shouldReceive('getInactiveTherapistCount')->once()->andReturn(3);
        $repository->shouldReceive('getNewTherapistsThisMonth')->once()->andReturn(2);
        $repository->shouldReceive('getStudentCount')->once()->andReturn(50);
        $repository->shouldReceive('getActiveStudentCount')->once()->andReturn(45);
        $repository->shouldReceive('getInactiveStudentCount')->once()->andReturn(5);
        $repository->shouldReceive('getNewStudentsThisMonth')->once()->andReturn(3);
        $repository->shouldReceive('getSSACount')->once()->andReturn(40);
        $repository->shouldReceive('getActiveSSACount')->once()->andReturn(35);
        $repository->shouldReceive('getPendingSSACount')->once()->andReturn(3);
        $repository->shouldReceive('getCompletedSSACount')->once()->andReturn(2);
        $repository->shouldReceive('getSSAsExpiringSoon')->once()->with(7)->andReturn(5);
        $repository->shouldReceive('getAverageSSAUtilization')->once()->andReturn(75);

        $service = new DashboardService($timezoneService, $repository);
        $result = $service->getKeyMetrics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('schools', $result);
        $this->assertArrayHasKey('therapists', $result);
        $this->assertArrayHasKey('students', $result);
        $this->assertArrayHasKey('ssas', $result);
        $this->assertSame(10, $result['schools']['total']);
        $this->assertSame(8, $result['schools']['active']);
    }

    public function test_get_critical_alerts_returns_array(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getSchoolsWithoutManagers')->once()->andReturn(0);
        $repository->shouldReceive('getInactiveTherapistsCount')->once()->andReturn(0);
        $repository->shouldReceive('getSSAsExpiringSoon')->once()->with(7)->andReturn(0);
        $repository->shouldReceive('getActiveStudentsCount')->once()->andReturn(45);
        $repository->shouldReceive('getStudentsWithActiveSSAsCount')->once()->andReturn(45);
        $repository->shouldReceive('getUnassignedSSAsCount')->once()->andReturn(0);

        $service = new DashboardService($timezoneService, $repository);
        $result = $service->getCriticalAlerts();

        $this->assertIsArray($result);
    }

    public function test_get_chart_data_returns_correct_structure(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getSSAStatusDistribution')->once()->andReturn(collect());
        $repository->shouldReceive('getTherapistsByPosition')->once()->andReturn(['labels' => [], 'data' => [], 'colors' => []]);
        $repository->shouldReceive('getUtilizationTrendData')->once()->andReturn(['labels' => [], 'tho_hours' => [], 'served_hours' => []]);

        $service = new DashboardService($timezoneService, $repository);
        $result = $service->getChartData();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ssa_distribution', $result);
        $this->assertArrayHasKey('therapist_by_position', $result);
        $this->assertArrayHasKey('utilization_trend', $result);
    }

    public function test_get_quick_actions_includes_invoice_and_billing_and_excludes_analytics(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $service = new DashboardService($timezoneService, $repository);
        $actions = $service->getQuickActions();

        $routes = array_map(static fn (array $a): string => (string) $a['route'], $actions);

        $this->assertContains('admin.invoices.create', $routes);
        $this->assertContains('admin.billing.therapist-bills.create', $routes);
        $this->assertNotContains('admin.analytics.index', $routes);
    }

    public function test_get_expiring_school_contract_events_includes_private_and_auto_extend_flags(): void
    {
        $school = new \App\Models\School([
            'display_name' => 'Test School',
            'is_private_student' => true,
            'is_auto_extend' => true,
        ]);
        $contract = new \App\Models\SchoolContract([
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        $contract->setRelation('school', $school);

        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getExpiringSchoolContracts')->once()
            ->with(30, 4)
            ->andReturn(collect([$contract]));

        $timezoneService->shouldReceive('toUserTimezone')->andReturn(now()->addDays(10));

        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn(new \App\Models\User);

        $service = new DashboardService($timezoneService, $repository);
        $events = $service->getExpiringSchoolContractEvents();

        $this->assertCount(1, $events);
        $this->assertSame('Test School', $events[0]['entity']);
        $this->assertTrue($events[0]['is_private_student']);
        $this->assertTrue($events[0]['is_auto_extend']);
    }

    public function test_get_expiring_ssa_events_returns_structured_data(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getExpiringSSAs')->once()
            ->with(30, 4)
            ->andReturn(collect());

        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn(new \App\Models\User);

        $service = new DashboardService($timezoneService, $repository);
        $events = $service->getExpiringSSAEvents();

        $this->assertIsArray($events);
        $this->assertCount(0, $events);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
