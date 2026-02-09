<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Services\AnalyticsService;
use Mockery;
use Tests\TestCase;

final class AnalyticsServiceTest extends TestCase
{
    public function test_get_schools_analytics_returns_correct_structure(): void
    {
        $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
        $repository->shouldReceive('getSchoolCount')->once()->andReturn(10);
        $repository->shouldReceive('getActiveSchoolCount')->once()->andReturn(8);
        $repository->shouldReceive('getInactiveSchoolCount')->once()->andReturn(2);
        $repository->shouldReceive('getSchoolsByState')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getSchoolsByType')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getSchoolsGrowthTrend')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getSchoolsByManager')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getRecentSchoolAdditions')->once()->with(10)->andReturn(collect([]));

        $service = new AnalyticsService($repository);
        $result = $service->getSchoolsAnalytics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('inactive', $result);
        $this->assertSame(10, $result['total']);
        $this->assertSame(8, $result['active']);
        $this->assertSame(2, $result['inactive']);
    }

    public function test_get_therapists_analytics_returns_correct_structure(): void
    {
        $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
        $repository->shouldReceive('getTherapistProfileCount')->once()->andReturn(15);
        $repository->shouldReceive('getActiveTherapistProfileCount')->once()->andReturn(12);
        $repository->shouldReceive('getTherapistsByPosition')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getTherapistsByEmployeeType')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getTherapistsByState')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getTherapistsGrowthTrend')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getRecentTherapistAdditions')->once()->with(10)->andReturn(collect([]));

        $service = new AnalyticsService($repository);
        $result = $service->getTherapistsAnalytics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertSame(15, $result['total']);
        $this->assertSame(12, $result['active']);
    }

    public function test_get_overall_analytics_returns_correct_structure(): void
    {
        $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
        $repository->shouldReceive('getSchoolCount')->once()->andReturn(10);
        $repository->shouldReceive('getActiveSchoolCount')->once()->andReturn(8);
        $repository->shouldReceive('getNewSchoolsInPeriod')->once()->andReturn(2);
        $repository->shouldReceive('getTherapistProfileCount')->once()->andReturn(15);
        $repository->shouldReceive('getActiveTherapistProfileCount')->once()->andReturn(12);
        $repository->shouldReceive('getNewTherapistProfilesInPeriod')->once()->andReturn(3);
        $repository->shouldReceive('getUserCount')->once()->andReturn(50);
        $repository->shouldReceive('getActiveUserCount')->once()->andReturn(45);
        $repository->shouldReceive('getUsersByRole')->once()->andReturn(['labels' => [], 'data' => []]);
        $repository->shouldReceive('getActivitySummary')->once()->andReturn(['labels' => [], 'data' => []]);

        $service = new AnalyticsService($repository);
        $result = $service->getOverallAnalytics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('schools', $result);
        $this->assertArrayHasKey('therapists', $result);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('activity_summary', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
