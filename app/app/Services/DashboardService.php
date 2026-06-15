<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Models\ServiceSupportAgreement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    public function __construct(
        private readonly UserTimezoneService $userTimezoneService,
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
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();
        $events = [];

        $expiringContracts = $this->repository->getExpiringSchoolContracts(30, $limit);

        foreach ($expiringContracts as $contract) {
            $school = $contract->school;
            $daysUntilExpiry = now()->diffInDays($contract->end_date);
            $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

            $events[] = [
                'title' => 'Contract Expiring',
                'entity' => $school !== null ? $school->display_name : 'School/Family',
                'due_date' => $contract->end_date,
                'due_date_local' => $this->userTimezoneService->toUserTimezone($contract->end_date, $currentUser),
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
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();
        $events = [];

        $expiringSSAs = $this->repository->getExpiringSSAs(30, $limit);

        foreach ($expiringSSAs as $ssa) {
            $daysUntilExpiry = now()->diffInDays($ssa->end_date);
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
                'due_date_local' => $ssa->end_date ? $this->userTimezoneService->toUserTimezone($ssa->end_date, $currentUser) : null,
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
                'title' => 'Create New SSA',
                'description' => 'Set up service agreement',
                'route' => 'admin.ssas.create',
                'icon' => 'document-add',
                'color' => 'primary',
            ],
            [
                'title' => 'Add School/Family',
                'description' => 'Onboard new school or family',
                'route' => 'admin.schools.create',
                'icon' => 'school',
                'color' => 'primary',
            ],
            [
                'title' => 'Add Therapist',
                'description' => 'Register new therapist',
                'route' => 'admin.therapists.create',
                'icon' => 'user-add',
                'color' => 'primary',
            ],
            [
                'title' => 'Add Student',
                'description' => 'Enroll new student',
                'route' => 'admin.students.create',
                'icon' => 'user',
                'color' => 'primary',
            ],
            [
                'title' => 'Create Invoice',
                'description' => 'Bill a school/family or client',
                'route' => 'admin.invoices.create',
                'icon' => 'invoice',
                'color' => 'primary',
            ],
            [
                'title' => 'Create Billing',
                'description' => 'New therapist bill',
                'route' => 'admin.billing.therapist-bills.create',
                'icon' => 'billing',
                'color' => 'primary',
            ],
        ];
    }
}
