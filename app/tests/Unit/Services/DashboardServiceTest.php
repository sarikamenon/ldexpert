<?php

declare(strict_types=1);

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DashboardService;

afterEach(function (): void {
    Mockery::close();
});

it('returns the expected key metrics structure', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);

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

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $result = $service->getKeyMetrics();

    expect($result)->toBeArray()
        ->toHaveKeys(['schools', 'therapists', 'students', 'ssas']);
    expect($result['schools']['total'])->toBe(10);
    expect($result['schools']['active'])->toBe(8);
});

it('returns critical alerts as an array', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);

    $repository->shouldReceive('getSchoolsWithoutManagers')->once()->andReturn(0);
    $repository->shouldReceive('getInactiveTherapistsCount')->once()->andReturn(0);
    $repository->shouldReceive('getSSAsExpiringSoon')->once()->with(7)->andReturn(0);
    $repository->shouldReceive('getActiveStudentsCount')->once()->andReturn(45);
    $repository->shouldReceive('getStudentsWithActiveSSAsCount')->once()->andReturn(45);
    $repository->shouldReceive('getUnassignedSSAsCount')->once()->andReturn(0);

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));

    expect($service->getCriticalAlerts())->toBeArray();
});

it('builds the account-balance chart from finance-summary totals', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);
    $financeSummary = Mockery::mock(FinanceSummaryRepositoryInterface::class);

    $financeSummary->shouldReceive('getRevenueCollected')->once()->andReturn(1000.0);
    $financeSummary->shouldReceive('getTherapistPayments')->once()->andReturn(600.0);
    $financeSummary->shouldReceive('getNonPayoutExpenses')->once()->andReturn(150.0);
    $repository->shouldReceive('getOpenSubRequestsByPosition')->once()
        ->andReturn(['labels' => [], 'data' => [], 'colors' => []]);

    $service = new DashboardService($repository, $financeSummary);
    $result = $service->getChartData();

    expect($result)->toHaveKeys(['account_balance', 'open_sub_requests_by_position']);
    expect($result['account_balance']['labels'])->toBe(['Income', 'Therapist Payouts', 'Other Expenses']);
    expect($result['account_balance']['data'])->toBe([1000.0, 600.0, 150.0]);
    expect($result['account_balance']['formatted'])->toBe(['$1,000.00', '$600.00', '$150.00']);
});

it('links quick actions to the expected routes', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $actions = $service->getQuickActions();

    $routes = collect($actions)->map(static fn (array $a): string => (string) $a['route'])->all();

    expect($routes)->toBe([
        'admin.schedule-calendar.index',
        'admin.session-logs.index',
        'admin.payments.invoices.index',
        'admin.students.index',
        'admin.ssas.create',
        'admin.leads.index',
    ]);

    expect($actions[1]['route'])->toBe('admin.session-logs.index');
    expect($actions[1]['route_params'])->toBe(['status' => 'submitted']);
});

it('includes private-student and auto-extend flags on expiring contract events', function (): void {
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
    $repository->shouldReceive('getExpiringSchoolContracts')->once()
        ->with(30, 4)
        ->andReturn(collect([$contract]));

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $events = $service->getExpiringSchoolContractEvents();

    expect($events)->toHaveCount(1);
    expect($events[0]['entity'])->toBe('Test School');
    expect($events[0]['is_private_student'])->toBeTrue();
    expect($events[0]['is_auto_extend'])->toBeTrue();
    expect($events[0]['due_date'])->toEqual($contract->end_date);
});

it('marks an already-expired contract as high priority', function (): void {
    $contract = new SchoolContract([
        'end_date' => now()->subDays(5)->toDateString(),
    ]);
    $contract->setRelation('school', new School(['display_name' => 'Past Due School']));

    $repository = Mockery::mock(DashboardRepositoryInterface::class);
    $repository->shouldReceive('getExpiringSchoolContracts')->once()
        ->with(30, 4)
        ->andReturn(collect([$contract]));

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $events = $service->getExpiringSchoolContractEvents();

    expect($events[0]['priority'])->toBe('high');
});

it('returns structured expiring-SSA events', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);
    $repository->shouldReceive('getExpiringSSAs')->once()
        ->with(30, 4)
        ->andReturn(collect());

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $events = $service->getExpiringSSAEvents();

    expect($events)->toBeArray()->toHaveCount(0);
});

it('returns compact pending-SSA rows', function (): void {
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
    $repository->shouldReceive('getPendingSSAs')->once()->with(5)->andReturn(collect([$ssa]));

    $dashboardService = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $events = $dashboardService->getPendingSSAEvents();

    expect($events)->toHaveCount(1);
    expect($events[0]['student'])->toBe('Jane Doe');
    expect($events[0]['school'])->toBe('Sunrise Academy');
    expect($events[0]['service'])->toBe('Speech');
    expect($events[0]['link'])->toContain('/ssas/42');
});

it('returns a null school for private students in pending-SSA rows', function (): void {
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
    $repository->shouldReceive('getPendingSSAs')->once()->with(5)->andReturn(collect([$ssa]));

    $dashboardService = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $events = $dashboardService->getPendingSSAEvents();

    expect($events[0]['school'])->toBeNull();
});

it('computes operational metrics including the contract activation rate', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);

    $repository->shouldReceive('getActiveSchoolsCount')->once()->andReturn(20);
    $repository->shouldReceive('getActiveTherapistsByUserStatusCount')->once()->andReturn(10);
    $repository->shouldReceive('getAverageSSADurationMonths')->once()->andReturn(6);
    $repository->shouldReceive('getServiceCompletionRate')->once()->andReturn(80);
    $repository->shouldReceive('getActiveSchoolContractsCount')->once()->andReturn(6);
    $repository->shouldReceive('getActiveTherapistContractsCount')->once()->andReturn(2);
    $repository->shouldReceive('getTotalContractsCount')->once()->andReturn(10);

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $metrics = $service->getOperationalMetrics();

    $byLabel = collect($metrics)->keyBy('label');

    // 20 active schools / 10 active therapists = 2.0:1
    expect($byLabel['School/Family–Therapist Ratio']['value'])->toBe('2.0:1');
    expect($byLabel['Avg SSA Duration']['value'])->toBe('6 months');
    expect($byLabel['Service Completion Rate']['value'])->toBe('80%');
    // (6 + 2) active / 10 total = 80%
    expect($byLabel['Active Contracts']['value'])->toBe('80%');
});

it('shows an N/A ratio when there are no active therapists', function (): void {
    $repository = Mockery::mock(DashboardRepositoryInterface::class);

    $repository->shouldReceive('getActiveSchoolsCount')->once()->andReturn(5);
    $repository->shouldReceive('getActiveTherapistsByUserStatusCount')->once()->andReturn(0);
    $repository->shouldReceive('getAverageSSADurationMonths')->once()->andReturn(0);
    $repository->shouldReceive('getServiceCompletionRate')->once()->andReturn(0);
    $repository->shouldReceive('getActiveSchoolContractsCount')->once()->andReturn(0);
    $repository->shouldReceive('getActiveTherapistContractsCount')->once()->andReturn(0);
    $repository->shouldReceive('getTotalContractsCount')->once()->andReturn(0);

    $service = new DashboardService($repository, Mockery::mock(FinanceSummaryRepositoryInterface::class));
    $metrics = $service->getOperationalMetrics();

    $byLabel = collect($metrics)->keyBy('label');

    expect($byLabel['School/Family–Therapist Ratio']['value'])->toBe('N/A');
    // 0 total contracts must not divide-by-zero
    expect($byLabel['Active Contracts']['value'])->toBe('0%');
});
