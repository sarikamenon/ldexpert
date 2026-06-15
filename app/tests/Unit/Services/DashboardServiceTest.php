<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
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

        $service = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
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

        $service = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
        $result = $service->getCriticalAlerts();

        $this->assertIsArray($result);
    }

    public function test_get_chart_data_returns_correct_structure(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);
        $financeSummary = Mockery::mock(FinanceSummaryRepositoryInterface::class);

        $financeSummary->shouldReceive('getRevenueCollected')->once()->andReturn(1000.0);
        $financeSummary->shouldReceive('getTherapistPayments')->once()->andReturn(600.0);
        $financeSummary->shouldReceive('getNonPayoutExpenses')->once()->andReturn(150.0);
        $repository->shouldReceive('getOpenSubRequestsByPosition')->once()->andReturn(['labels' => [], 'data' => [], 'colors' => []]);

        $service = new DashboardService($timezoneService, $repository, $financeSummary);
        $result = $service->getChartData();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('account_balance', $result);
        $this->assertArrayHasKey('open_sub_requests_by_position', $result);
        $this->assertArrayNotHasKey('therapist_by_position', $result);

        $this->assertSame(['Income', 'Therapist Payouts', 'Other Expenses'], $result['account_balance']['labels']);
        $this->assertSame([1000.0, 600.0, 150.0], $result['account_balance']['data']);
        $this->assertSame(['$1,000.00', '$600.00', '$150.00'], $result['account_balance']['formatted']);
    }

    public function test_get_quick_actions_includes_invoice_and_billing_and_excludes_analytics(): void
    {
        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $service = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
        $actions = $service->getQuickActions();

        $routes = array_map(static fn (array $a): string => (string) $a['route'], $actions);

        $this->assertContains('admin.invoices.create', $routes);
        $this->assertContains('admin.billing.therapist-bills.create', $routes);
        $this->assertNotContains('admin.analytics.index', $routes);
    }

    public function test_get_expiring_school_contract_events_includes_private_and_auto_extend_flags(): void
    {
        $school = new School([
            'display_name' => 'Test School',
            'is_private_student' => true,
            'is_auto_extend' => true,
        ]);
        $contract = new SchoolContract([
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        $contract->setRelation('school', $school);

        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getExpiringSchoolContracts')->once()
            ->with(30, 4)
            ->andReturn(collect([$contract]));

        $timezoneService->shouldReceive('toUserTimezone')->andReturn(now()->addDays(10));

        Auth::shouldReceive('user')->andReturn(new User);

        $service = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
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

        Auth::shouldReceive('user')->andReturn(new User);

        $service = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
        $events = $service->getExpiringSSAEvents();

        $this->assertIsArray($events);
        $this->assertCount(0, $events);
    }

    public function test_get_pending_ssa_events_returns_compact_rows(): void
    {
        $school = new School(['display_name' => 'Sunrise Academy']);
        $profile = new StudentProfile(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $profile->setRelation('school', $school);
        $student = new User;
        $student->setRelation('studentProfile', $profile);
        $service = new Service(['name' => 'Speech']);

        $ssa = new ServiceSupportAgreement;
        $ssa->id = 42;
        $ssa->setRelation('student', $student);
        $ssa->setRelation('primaryService', $service);

        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getPendingSSAs')->once()->with(5)->andReturn(collect([$ssa]));

        $dashboardService = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
        $events = $dashboardService->getPendingSSAEvents();

        $this->assertCount(1, $events);
        $this->assertSame('Jane Doe', $events[0]['student']);
        $this->assertSame('Sunrise Academy', $events[0]['school']);
        $this->assertSame('Speech', $events[0]['service']);
        $this->assertStringContainsString('/ssas/42', $events[0]['link']);
    }

    public function test_get_pending_ssa_events_school_is_null_for_private_students(): void
    {
        $profile = new StudentProfile(['first_name' => 'John', 'last_name' => 'Smith']);
        $profile->setRelation('school', null);
        $student = new User;
        $student->setRelation('studentProfile', $profile);
        $service = new Service(['name' => 'OT']);

        $ssa = new ServiceSupportAgreement;
        $ssa->id = 99;
        $ssa->setRelation('student', $student);
        $ssa->setRelation('primaryService', $service);

        $repository = Mockery::mock(DashboardRepositoryInterface::class);
        $timezoneService = Mockery::mock(UserTimezoneService::class);

        $repository->shouldReceive('getPendingSSAs')->once()->with(5)->andReturn(collect([$ssa]));

        $dashboardService = new DashboardService($timezoneService, $repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
        $events = $dashboardService->getPendingSSAEvents();

        $this->assertNull($events[0]['school']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
