<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Enums\SessionLogStatus;
use App\Models\ServiceSupportAgreement;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository,
        private readonly FinanceSummaryRepositoryInterface $financeSummaryRepository,
    ) {}

    /** @return array<string, mixed> */
    public function getKeyMetrics(): array
    {
        return [
            'schools' => [
                'total' => $this->repository->getSchoolCount(),
                'active' => $this->repository->getActiveSchoolCount(),
                'inactive' => $this->repository->getInactiveSchoolCount(),
                'new_this_month' => $this->repository->getNewSchoolsThisMonth(),
            ],
            'therapists' => [
                'total' => $this->repository->getTherapistCount(),
                'active' => $this->repository->getActiveTherapistCount(),
                'inactive' => $this->repository->getInactiveTherapistCount(),
                'new_this_month' => $this->repository->getNewTherapistsThisMonth(),
            ],
            'students' => [
                'total' => $this->repository->getStudentCount(),
                'active' => $this->repository->getActiveStudentCount(),
                'inactive' => $this->repository->getInactiveStudentCount(),
                'new_this_month' => $this->repository->getNewStudentsThisMonth(),
            ],
            'ssas' => [
                'total' => $this->repository->getSSACount(),
                'active' => $this->repository->getActiveSSACount(),
                'pending' => $this->repository->getPendingSSACount(),
                'completed' => $this->repository->getCompletedSSACount(),
                'expiring_soon' => $this->repository->getSSAsExpiringSoon(7),
                'avg_utilization' => $this->repository->getAverageSSAUtilization(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getCriticalAlerts(): array
    {
        $alerts = [];

        $schoolsWithoutManagers = $this->repository->getSchoolsWithoutManagers();
        if ($schoolsWithoutManagers > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "{$schoolsWithoutManagers} ".($schoolsWithoutManagers === 1 ? 'School/Family' : 'Schools/Families').' without assigned managers',
                'link' => route('admin.schools.index'),
                'icon' => 'alert',
            ];
        }

        $inactiveTherapists = $this->repository->getInactiveTherapistsCount();
        if ($inactiveTherapists > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$inactiveTherapists} Inactive ".($inactiveTherapists === 1 ? 'therapist' : 'therapists'),
                'link' => route('admin.therapists.index', ['show_deactivated' => 1]),
                'icon' => 'user',
            ];
        }

        $ssasExpiringSoon = $this->repository->getSSAsExpiringSoon(7);
        if ($ssasExpiringSoon > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$ssasExpiringSoon} ".($ssasExpiringSoon === 1 ? 'SSA' : 'SSAs').' expiring in next 7 days',
                'link' => route('admin.ssas.index'),
                'icon' => 'calendar',
            ];
        }

        $activeStudents = $this->repository->getActiveStudentsCount();
        $studentsWithActiveSSAs = $this->repository->getStudentsWithActiveSSAsCount();
        $studentsNeedingSSA = max(0, $activeStudents - $studentsWithActiveSSAs);
        if ($studentsNeedingSSA > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$studentsNeedingSSA} ".($studentsNeedingSSA === 1 ? 'Student' : 'Students').' without active SSAs',
                'link' => route('admin.students.index'),
                'icon' => 'user',
            ];
        }

        $unassignedSSAs = $this->repository->getUnassignedSSAsCount();
        if ($unassignedSSAs > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$unassignedSSAs} ".($unassignedSSAs === 1 ? 'SSA' : 'SSAs').' pending therapist assignment',
                'link' => route('admin.ssas.index', ['status' => 'pending']),
                'icon' => 'document',
            ];
        }

        return $alerts;
    }

    /** @return array<string, mixed> */
    public function getChartData(): array
    {
        return [
            'account_balance' => $this->getAccountBalanceChartData(),
            'open_sub_requests_by_position' => $this->repository->getOpenSubRequestsByPosition(),
        ];
    }

    /**
     * Income, therapist payouts, and other expenses across all ledger activity.
     *
     * @return array{labels: array<int, string>, data: array<int, float>, formatted: array<int, string>, colors: array<int, string>}
     */
    private function getAccountBalanceChartData(): array
    {
        // These finance-summary methods return all-time totals; the date range is
        // not honoured by the repository, so the bounds below are nominal (epoch
        // to now) and exist only to satisfy the shared interface signature.
        $start = Carbon::createFromTimestamp(0);
        $end = now();

        $income = $this->financeSummaryRepository->getRevenueCollected($start, $end);
        $therapistPayouts = $this->financeSummaryRepository->getTherapistPayments($start, $end);
        $otherExpenses = $this->financeSummaryRepository->getNonPayoutExpenses($start, $end);

        $values = [$income, $therapistPayouts, $otherExpenses];

        return [
            'labels' => ['Income', 'Therapist Payouts', 'Other Expenses'],
            'data' => $values,
            'formatted' => collect($values)
                ->map(static fn (float $v): string => '$'.number_format($v, 2))
                ->all(),
            'colors' => ['#10b981', '#5563b8', '#f59e0b'],
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function getUpcomingEvents(): array
    {
        return [
            'schools' => $this->getExpiringSchoolContractEvents(),
            'ssas' => $this->getExpiringSSAEvents(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getExpiringSchoolContractEvents(int $limit = 4): array
    {
        $events = [];

        $expiringContracts = $this->repository->getExpiringSchoolContracts(30, $limit);

        foreach ($expiringContracts as $contract) {
            $school = $contract->school;
            // end_date is a pure calendar date (cast 'date') — compare day-to-day,
            // never TZ-convert. Signed diff so already-expired contracts read negative
            // and fall into the high-priority bucket rather than looking far off.
            $daysUntilExpiry = now()->startOfDay()->diffInDays($contract->end_date, false);
            $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

            $events[] = [
                'title' => 'Contract Expiring',
                'entity' => $school !== null ? $school->display_name : 'School/Family',
                'due_date' => $contract->end_date,
                'priority' => $priority,
                'is_private_student' => (bool) ($school?->is_private_student),
                'is_auto_extend' => (bool) ($school?->is_auto_extend),
            ];
        }

        return $events;
    }

    /** @return array<int, array<string, mixed>> */
    public function getExpiringSSAEvents(int $limit = 4): array
    {
        $events = [];

        $expiringSSAs = $this->repository->getExpiringSSAs(30, $limit);

        foreach ($expiringSSAs as $ssa) {
            // end_date is a pure calendar date (cast 'date') — compare day-to-day,
            // never TZ-convert. Signed diff so already-expired SSAs read negative.
            $daysUntilExpiry = now()->startOfDay()->diffInDays($ssa->end_date, false);
            $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

            $studentName = $ssa->student?->studentProfile
                ? "{$ssa->student->studentProfile->first_name} {$ssa->student->studentProfile->last_name}"
                : 'Student';
            $serviceName = $ssa->primaryService !== null ? $ssa->primaryService->name : 'Service';
            $school = $ssa->student?->studentProfile?->school;

            $events[] = [
                'title' => 'SSA Expiring',
                'entity' => "{$studentName} - {$serviceName}",
                'due_date' => $ssa->end_date,
                'priority' => $priority,
                'is_private_student' => (bool) ($school?->is_private_student),
                'is_auto_extend' => (bool) ($school?->is_auto_extend),
            ];
        }

        return $events;
    }

    /** @return array<int, array<string, mixed>> */
    public function getPendingSSAEvents(int $limit = 5): array
    {
        $pendingSSAs = $this->repository->getPendingSSAs($limit);

        return $pendingSSAs->map(function (ServiceSupportAgreement $ssa): array {
            $studentProfile = $ssa->student?->studentProfile;
            $studentName = $studentProfile
                ? "{$studentProfile->first_name} {$studentProfile->last_name}"
                : 'Student';
            $serviceName = $ssa->primaryService !== null ? $ssa->primaryService->name : 'Service';
            $schoolName = $studentProfile?->school?->name;

            return [
                'student' => $studentName,
                'school' => $schoolName,
                'service' => $serviceName,
                'link' => route('admin.ssas.show', $ssa),
            ];
        })->all();
    }

    public function getPendingSSACount(): int
    {
        return $this->repository->getPendingSSACount();
    }

    /** @return array<int, array<string, mixed>> */
    public function getOperationalMetrics(): array
    {
        $activeSchools = $this->repository->getActiveSchoolsCount();
        $activeTherapists = $this->repository->getActiveTherapistsByUserStatusCount();
        $avgSSADurationMonths = $this->repository->getAverageSSADurationMonths();
        $completionRate = $this->repository->getServiceCompletionRate();

        $activeSchoolContracts = $this->repository->getActiveSchoolContractsCount();
        $activeTherapistContracts = $this->repository->getActiveTherapistContractsCount();
        $totalContracts = $this->repository->getTotalContractsCount();
        $activeContracts = $activeSchoolContracts + $activeTherapistContracts;
        $contractActivationRate = $totalContracts > 0 ? round(($activeContracts / $totalContracts) * 100) : 0;

        return [
            [
                'label' => 'School/Family–Therapist Ratio',
                'value' => $activeTherapists > 0 ? number_format($activeSchools / $activeTherapists, 1).':1' : 'N/A',
                'help' => 'Active schools/families divided by active therapists, shown as a ratio.',
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Avg SSA Duration',
                'value' => "{$avgSSADurationMonths} months",
                'help' => 'Average length of SSA agreements in months.',
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Service Completion Rate',
                'value' => "{$completionRate}%",
                'help' => 'Percent of services completed out of all assigned services.',
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Active Contracts',
                'value' => "{$contractActivationRate}%",
                'help' => 'Percent of school/family and therapist contracts currently active.',
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getQuickActions(): array
    {
        return [
            [
                'title' => 'Schedule Calendar',
                'description' => 'Schedule calendar',
                'route' => 'admin.schedule-calendar.index',
                'icon_path' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                'color' => 'primary',
            ],
            [
                'title' => 'Submitted Sessions',
                'description' => 'Review Submitted sessions',
                'route' => 'admin.session-logs.index',
                'route_params' => ['status' => SessionLogStatus::SUBMITTED->value],
                'icon_path' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                'color' => 'primary',
            ],
            [
                'title' => 'Invoice Payments',
                'description' => 'Manage invoice payments',
                'route' => 'admin.payments.invoices.index',
                'icon_path' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'color' => 'primary',
            ],
            [
                'title' => 'Student List',
                'description' => 'Browse all students',
                'route' => 'admin.students.index',
                'icon_path' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                'color' => 'primary',
            ],
            [
                'title' => 'Create SSA',
                'description' => 'Set up service agreement',
                'route' => 'admin.ssas.create',
                'icon_path' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'color' => 'primary',
            ],
            [
                'title' => 'Lead List',
                'description' => 'Browse and manage leads',
                'route' => 'admin.leads.index',
                'icon_path' => 'M4 6h16M4 10h16M4 14h16M4 18h16',
                'color' => 'primary',
            ],
        ];
    }
}
