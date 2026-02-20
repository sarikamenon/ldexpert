<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Enums\SSAStatus;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly UserTimezoneService $userTimezoneService,
        private readonly DashboardRepositoryInterface $repository,
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
                'message' => "{$schoolsWithoutManagers} ".($schoolsWithoutManagers === 1 ? 'School' : 'Schools').' without assigned managers',
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
        $ssaDistribution = $this->repository->getSSAStatusDistribution();

        $pending = $ssaDistribution->get(SSAStatus::PENDING->value);
        $active = $ssaDistribution->get(SSAStatus::ACTIVE->value);
        $completed = $ssaDistribution->get(SSAStatus::COMPLETED->value);
        $deactivated = $ssaDistribution->get(SSAStatus::DEACTIVATED->value);
        $ssaDistributionData = [
            'Pending' => $pending !== null ? $pending->count : 0,
            'Active' => $active !== null ? $active->count : 0,
            'Completed' => $completed !== null ? $completed->count : 0,
            'Deactivated' => $deactivated !== null ? $deactivated->count : 0,
        ];

        return [
            'ssa_distribution' => [
                'labels' => array_keys($ssaDistributionData),
                'data' => array_values($ssaDistributionData),
                'colors' => ['#f59e0b', '#10b981', '#3b82f6', '#6b7280'],
            ],
            'therapist_by_position' => $this->repository->getTherapistsByPosition(),
            'utilization_trend' => $this->repository->getUtilizationTrendData(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getRecentActivity(int $limit = 10): array
    {
        $logs = $this->activityLogService
            ->recent($limit)
            ->withUserTimezone(Auth::user());

        return $logs
            ->map(function (ActivityLog $log): array {
                return [
                    'type' => $log->action,
                    'description' => $log->description ?? $this->fallbackActivityDescription($log),
                    'user' => $log->user !== null ? $log->user->name : 'System',
                    'created_at' => $log->created_at,
                    'created_at_local' => $log->getAttribute('created_at_local') ?? $log->created_at,
                    'icon' => $this->resolveActivityIcon($log),
                    'color' => $this->resolveActivityColor($log),
                ];
            })
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function getUpcomingEvents(): array
    {
        $events = [];

        $expiringSSAs = $this->repository->getExpiringSSAs(30, 4);

        foreach ($expiringSSAs as $ssa) {
            $daysUntilExpiry = now()->diffInDays($ssa->end_date);
            $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

            $studentName = $ssa->student->studentProfile
                ? "{$ssa->student->studentProfile->first_name} {$ssa->student->studentProfile->last_name}"
                : 'Student';
            $serviceName = $ssa->primaryService !== null ? $ssa->primaryService->name : 'Service';

            $events[] = [
                'title' => 'SSA Expiring',
                'entity' => "{$studentName} - {$serviceName}",
                'due_date' => $ssa->end_date,
                'due_date_local' => $this->userTimezoneService->toUserTimezone($ssa->end_date, Auth::user()),
                'priority' => $priority,
            ];
        }

        if (count($events) < 4) {
            $expiringContracts = $this->repository->getExpiringSchoolContracts(30, 4 - count($events));

            foreach ($expiringContracts as $contract) {
                $daysUntilExpiry = now()->diffInDays($contract->end_date);
                $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

                $events[] = [
                    'title' => 'Contract Expiring',
                    'entity' => "School Contract - {$contract->school->display_name}",
                    'due_date' => $contract->end_date,
                    'due_date_local' => $this->userTimezoneService->toUserTimezone($contract->end_date, Auth::user()),
                    'priority' => $priority,
                ];
            }
        }

        usort($events, fn ($a, $b) => $a['due_date'] <=> $b['due_date']);

        return array_slice($events, 0, 4);
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
                'label' => 'School-Therapist Ratio',
                'value' => $activeTherapists > 0 ? number_format($activeSchools / $activeTherapists, 1).':1' : 'N/A',
                'help' => 'Active schools divided by active therapists, shown as a ratio.',
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
                'help' => 'Percent of school and therapist contracts currently active.',
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
                'title' => 'Add School',
                'description' => 'Onboard new school',
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
                'title' => 'View Analytics',
                'description' => 'Detailed insights',
                'route' => 'admin.analytics.index',
                'icon' => 'chart',
                'color' => 'secondary',
            ],
            [
                'title' => 'Activity Logs',
                'description' => 'Audit trail',
                'route' => 'admin.activity-logs.index',
                'icon' => 'list',
                'color' => 'secondary',
            ],
        ];
    }

    private function fallbackActivityDescription(ActivityLog $log): string
    {
        $modelName = class_basename($log->model_type ?? 'Record');
        $action = Str::headline($log->action ?? 'activity');

        return "{$modelName} {$action}";
    }

    private function resolveActivityIcon(ActivityLog $log): string
    {
        $model = strtolower(class_basename($log->model_type ?? 'record'));

        return match ($model) {
            'school' => 'school',
            'therapistprofile' => 'user',
            'studentprofile' => 'user',
            'servicesupportagreement' => 'document',
            'service' => 'settings',
            default => 'activity',
        };
    }

    private function resolveActivityColor(ActivityLog $log): string
    {
        $action = $log->action ?? '';

        return match (true) {
            str_contains($action, 'created') => 'primary',
            str_contains($action, 'updated') => 'secondary',
            str_contains($action, 'deleted') => 'danger',
            str_contains($action, 'status') => 'warning',
            default => 'accent',
        };
    }
}
