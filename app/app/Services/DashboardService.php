<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Time\UserTimezoneService;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly UserTimezoneService $userTimezoneService,
    ) {}

    public function getKeyMetrics(): array
    {
        // Temporarily disabled caching to show real-time data
        // return Cache::remember('dashboard.key.metrics', now()->addSeconds(30), function () {

        // Real data for Schools
        $totalSchools = School::count();
        $activeSchools = School::where('status', SchoolStatus::ACTIVE)->count();
        $inactiveSchools = School::where('status', SchoolStatus::INACTIVE)->count();
        $newSchoolsThisMonth = School::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Real data for Therapists (align with therapist listing)
        $therapistQuery = User::query()->where('role', Role::THERAPIST);
        $totalTherapists = (clone $therapistQuery)->count();
        $activeTherapists = (clone $therapistQuery)
            ->where('status', UserStatus::ACTIVE)
            ->count();
        $inactiveTherapists = (clone $therapistQuery)
            ->where('status', UserStatus::INACTIVE)
            ->count();
        $newTherapistsThisMonth = (clone $therapistQuery)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Real data for Students
        $studentQuery = User::query()->where('role', Role::STUDENT);
        $totalStudents = (clone $studentQuery)->count();
        $activeStudents = (clone $studentQuery)
            ->where('status', UserStatus::ACTIVE)
            ->count();
        $inactiveStudents = (clone $studentQuery)
            ->where('status', UserStatus::INACTIVE)
            ->count();
        $newStudentsThisMonth = (clone $studentQuery)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Real data for SSAs
        $totalSSAs = ServiceSupportAgreement::count();
        $activeSSAs = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)->count();
        $pendingSSAs = ServiceSupportAgreement::where('status', SSAStatus::PENDING)->count();
        $completedSSAs = ServiceSupportAgreement::where('status', SSAStatus::COMPLETED)->count();
        $ssasExpiringSoon = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->whereBetween('end_date', [now(), now()->addDays(7)])
            ->count();

        // Calculate average utilization (served_minutes / tho_minutes)
        // Both are already in minutes, so we just calculate the percentage
        $ssaUtilization = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->where('tho_minutes', '>', 0)
            ->selectRaw('AVG(CASE WHEN tho_minutes > 0 THEN (CAST(served_minutes AS DECIMAL(10,2)) / CAST(tho_minutes AS DECIMAL(10,2))) * 100 ELSE 0 END) as avg_utilization')
            ->value('avg_utilization');
        $avgUtilization = $ssaUtilization ? (int) round((float) $ssaUtilization) : 0;

        return [
            'schools' => [
                'total' => $totalSchools,
                'active' => $activeSchools,
                'inactive' => $inactiveSchools,
                'new_this_month' => $newSchoolsThisMonth,
            ],
            'therapists' => [
                'total' => $totalTherapists,
                'active' => $activeTherapists,
                'inactive' => $inactiveTherapists,
                'new_this_month' => $newTherapistsThisMonth,
            ],
            'students' => [
                'total' => $totalStudents,
                'active' => $activeStudents,
                'inactive' => $inactiveStudents,
                'new_this_month' => $newStudentsThisMonth,
            ],
            'ssas' => [
                'total' => $totalSSAs,
                'active' => $activeSSAs,
                'pending' => $pendingSSAs,
                'completed' => $completedSSAs,
                'expiring_soon' => $ssasExpiringSoon,
                'avg_utilization' => $avgUtilization,
            ],
        ];
        // });
    }

    public function getCriticalAlerts(): array
    {
        $alerts = [];

        // Real alert: Schools without managers
        $schoolsWithoutManagers = School::whereNull('manager_id')->count();
        if ($schoolsWithoutManagers > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "{$schoolsWithoutManagers} " . ($schoolsWithoutManagers === 1 ? 'School' : 'Schools') . " without assigned managers",
                'link' => route('admin.schools.index'),
                'icon' => 'alert',
            ];
        }

        // Real alert: Inactive therapists that might need attention
        $inactiveTherapists = TherapistProfile::whereHas('user', function ($query) {
            $query->where('status', '!=', UserStatus::ACTIVE);
        })->count();

        if ($inactiveTherapists > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$inactiveTherapists} Inactive " . ($inactiveTherapists === 1 ? 'therapist' : 'therapists'),
                'link' => route('admin.therapists.index', ['show_deactivated' => 1]),
                'icon' => 'user',
            ];
        }

        // Real alert: SSAs expiring soon
        $ssasExpiringSoon = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->whereBetween('end_date', [now(), now()->addDays(7)])
            ->count();
        if ($ssasExpiringSoon > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$ssasExpiringSoon} " . ($ssasExpiringSoon === 1 ? 'SSA' : 'SSAs') . " expiring in next 7 days",
                'link' => route('admin.ssas.index'),
                'icon' => 'calendar',
            ];
        }

        // Real alert: Students without active SSAs
        $activeStudents = User::where('role', Role::STUDENT)
            ->where('status', UserStatus::ACTIVE)
            ->count();
        $studentsWithActiveSSAs = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->distinct('student_id')
            ->count('student_id');
        $studentsNeedingSSA = max(0, $activeStudents - $studentsWithActiveSSAs);
        if ($studentsNeedingSSA > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$studentsNeedingSSA} " . ($studentsNeedingSSA === 1 ? 'Student' : 'Students') . " without active SSAs",
                'link' => route('admin.students.index'),
                'icon' => 'user',
            ];
        }

        // Real alert: Unassigned SSAs
        $unassignedSSAs = ServiceSupportAgreement::where('status', SSAStatus::PENDING)
            ->whereNull('assigned_therapist_id')
            ->count();
        if ($unassignedSSAs > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$unassignedSSAs} " . ($unassignedSSAs === 1 ? 'SSA' : 'SSAs') . " pending therapist assignment",
                'link' => route('admin.ssas.index', ['status' => 'pending']),
                'icon' => 'document',
            ];
        }

        return $alerts;
    }

    public function getChartData(): array
    {
        // Real SSA status distribution
        $ssaDistribution = ServiceSupportAgreement::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $ssaDistributionData = [
            'Pending' => $ssaDistribution->get(SSAStatus::PENDING->value)?->count ?? 0,
            'Active' => $ssaDistribution->get(SSAStatus::ACTIVE->value)?->count ?? 0,
            'Completed' => $ssaDistribution->get(SSAStatus::COMPLETED->value)?->count ?? 0,
            'Deactivated' => $ssaDistribution->get(SSAStatus::DEACTIVATED->value)?->count ?? 0,
        ];

        return [
            'ssa_distribution' => [
                'labels' => array_keys($ssaDistributionData),
                'data' => array_values($ssaDistributionData),
                'colors' => ['#f59e0b', '#10b981', '#3b82f6', '#6b7280'],
            ],
            'therapist_by_position' => $this->getTherapistByPosition(),
            'utilization_trend' => $this->getUtilizationTrend(),
        ];
    }

    public function getRecentActivity(int $limit = 10): array
    {
        $logs = $this->activityLogService
            ->recent($limit)
            ->withUserTimezone(auth()->user());

        return $logs
            ->map(function (ActivityLog $log): array {
                return [
                    'type' => $log->action,
                    'description' => $log->description ?? $this->fallbackActivityDescription($log),
                    'user' => $log->user?->name ?? 'System',
                    'created_at' => $log->created_at,
                    'created_at_local' => $log->getAttribute('created_at_local') ?? $log->created_at,
                    'icon' => $this->resolveActivityIcon($log),
                    'color' => $this->resolveActivityColor($log),
                ];
            })
            ->toArray();
    }

    public function getUpcomingEvents(): array
    {
        $events = [];

        // Real data: SSAs expiring in next 30 days
        $expiringSSAs = ServiceSupportAgreement::with(['student.studentProfile', 'primaryService'])
            ->where('status', SSAStatus::ACTIVE)
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->orderBy('end_date', 'asc')
            ->take(4)
            ->get();

        foreach ($expiringSSAs as $ssa) {
            $daysUntilExpiry = now()->diffInDays($ssa->end_date);
            $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

            $studentName = $ssa->student->studentProfile
                ? "{$ssa->student->studentProfile->first_name} {$ssa->student->studentProfile->last_name}"
                : 'Student';
            $serviceName = $ssa->primaryService?->name ?? 'Service';

            $events[] = [
                'title' => 'SSA Expiring',
                'entity' => "{$studentName} - {$serviceName}",
                'due_date' => $ssa->end_date,
                'due_date_local' => $this->userTimezoneService->toUserTimezone($ssa->end_date, auth()->user()),
                'priority' => $priority,
            ];
        }

        // If we have fewer than 4 events, pad with contract expiration events
        if (count($events) < 4) {
            $expiringContracts = SchoolContract::with('school')
                ->where('status', ContractStatus::ACTIVE)
                ->whereBetween('end_date', [now(), now()->addDays(30)])
                ->orderBy('end_date', 'asc')
                ->take(4 - count($events))
                ->get();

            foreach ($expiringContracts as $contract) {
                $daysUntilExpiry = now()->diffInDays($contract->end_date);
                $priority = $daysUntilExpiry <= 7 ? 'high' : ($daysUntilExpiry <= 14 ? 'medium' : 'low');

                $events[] = [
                    'title' => 'Contract Expiring',
                    'entity' => "School Contract - {$contract->school->display_name}",
                    'due_date' => $contract->end_date,
                    'due_date_local' => $this->userTimezoneService->toUserTimezone($contract->end_date, auth()->user()),
                    'priority' => $priority,
                ];
            }
        }

        // Sort by due date and return top 4
        usort($events, fn($a, $b) => $a['due_date'] <=> $b['due_date']);

        return array_slice($events, 0, 4);
    }

    public function getOperationalMetrics(): array
    {
        $totalSchools = School::count();
        $totalTherapists = TherapistProfile::count();
        $activeSchools = School::where('status', SchoolStatus::ACTIVE)->count();
        $activeTherapists = User::where('role', Role::THERAPIST)
            ->where('status', UserStatus::ACTIVE)
            ->count();

        // Calculate average SSA duration in months
        // Using MySQL TIMESTAMPDIFF to get difference in months
        $avgSSADuration = ServiceSupportAgreement::whereNotNull('end_date')
            ->whereNotNull('start_date')
            ->selectRaw('AVG(TIMESTAMPDIFF(MONTH, start_date, end_date)) as avg_months')
            ->value('avg_months');
        $avgSSADurationMonths = $avgSSADuration ? number_format((float) $avgSSADuration, 1) : '0.0';

        // Calculate service completion rate (completed SSAs / total SSAs)
        $totalSSAs = ServiceSupportAgreement::count();
        $completedSSAs = ServiceSupportAgreement::where('status', SSAStatus::COMPLETED)->count();
        $completionRate = $totalSSAs > 0 ? round(($completedSSAs / $totalSSAs) * 100) : 0;

        // Calculate active contract ratio
        $activeSchoolContracts = SchoolContract::where('status', ContractStatus::ACTIVE)->count();
        $activeTherapistContracts = TherapistContract::where('status', ContractStatus::ACTIVE)->count();
        $totalContracts = SchoolContract::count() + TherapistContract::count();
        $activeContracts = $activeSchoolContracts + $activeTherapistContracts;
        $contractActivationRate = $totalContracts > 0 ? round(($activeContracts / $totalContracts) * 100) : 0;

        return [
            [
                'label' => 'School-Therapist Ratio',
                'value' => $activeTherapists > 0 ? number_format($activeSchools / $activeTherapists, 1) . ':1' : 'N/A',
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Avg SSA Duration',
                'value' => "{$avgSSADurationMonths} months",
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Service Completion Rate',
                'value' => "{$completionRate}%",
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Active Contracts',
                'value' => "{$contractActivationRate}%",
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
        ];
    }

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

    private function getTherapistByPosition(): array
    {
        $therapistsByPosition = DB::table('therapist_profiles')
            ->select('position', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('position')
            ->get();

        if ($therapistsByPosition->isEmpty()) {
            // Return dummy data if no therapists yet
            return [
                'labels' => ['SLP', 'OT', 'PT', 'LCSW'],
                'data' => [45, 32, 28, 15],
                'colors' => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
            ];
        }

        return [
            'labels' => $therapistsByPosition->pluck('position')->toArray(),
            'data' => $therapistsByPosition->pluck('count')->toArray(),
            'colors' => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
        ];
    }

    private function getLast30DaysLabels(): array
    {
        $labels = [];
        for ($i = 29; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('M d');
        }
        return $labels;
    }

    private function getUtilizationTrend(): array
    {
        $labels = $this->getLast30DaysLabels();
        $thoMinutes = [];
        $servedMinutes = [];

        // Get SSA data for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);

            // Sum THO minutes for SSAs active on this date
            $thoSum = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
                ->whereDate('start_date', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $date);
                })
                ->sum('tho_minutes');

            // Sum served minutes for SSAs active on this date
            $servedSum = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
                ->whereDate('start_date', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $date);
                })
                ->sum('served_minutes');

            $thoMinutes[] = (int) ($thoSum ?? 0);
            $servedMinutes[] = (int) ($servedSum ?? 0);
        }

        return [
            'labels' => $labels,
            'tho_minutes' => $thoMinutes,
            'served_minutes' => $servedMinutes,
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
