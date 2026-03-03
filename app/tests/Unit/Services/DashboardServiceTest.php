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
        $repository->shouldReceive('getUtilizationTrendData')->once()->andReturn(['labels' => [], 'tho_minutes' => [], 'served_minutes' => []]);

        $service = new DashboardService($timezoneService, $repository);
        $result = $service->getChartData();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ssa_distribution', $result);
        $this->assertArrayHasKey('therapist_by_position', $result);
        $this->assertArrayHasKey('utilization_trend', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
