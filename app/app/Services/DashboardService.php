<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
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

        // Dummy data for Students (not yet implemented)
        $totalStudents = 245;
        $activeStudents = 198;
        $studentsNeedingSSA = 12;
        $newStudentsThisMonth = 8;

        // Dummy data for SSAs (not yet implemented)
        $activeSSAs = 187;
        $pendingSSAs = 23;
        $ssasExpiringSoon = 15;
        $avgUtilization = 87;

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
                'needing_ssa' => $studentsNeedingSSA,
                'new_this_month' => $newStudentsThisMonth,
            ],
            'ssas' => [
                'active' => $activeSSAs,
                'pending' => $pendingSSAs,
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

        // Dummy alerts for not-yet-implemented modules (will be replaced when modules are ready)
        $alerts[] = [
            'type' => 'warning',
            'message' => '15 SSAs expiring in next 7 days',
            'link' => '#', // TODO: Link to filtered SSA list when SSA module is ready
            'icon' => 'calendar',
        ];

        $alerts[] = [
            'type' => 'warning',
            'message' => '12 Students without active SSAs',
            'link' => '#', // TODO: Link to filtered student list when Student module is ready
            'icon' => 'user',
        ];

        $alerts[] = [
            'type' => 'info',
            'message' => '8 Sessions awaiting documentation',
            'link' => '#', // TODO: Link to filtered session list when Session module is ready
            'icon' => 'document',
        ];

        return $alerts;
    }

    public function getChartData(): array
    {
        return [
            'ssa_distribution' => [
                'labels' => ['Pending', 'Active', 'Completed', 'Deactivated'],
                'data' => [23, 187, 456, 34],
                'colors' => ['#f59e0b', '#10b981', '#3b82f6', '#6b7280'],
            ],
            'therapist_by_position' => $this->getTherapistByPosition(),
            'utilization_trend' => [
                'labels' => $this->getLast30DaysLabels(),
                'tho_minutes' => $this->generateTrendData(8000, 12000, 30),
                'served_minutes' => $this->generateTrendData(7000, 10500, 30),
            ],
        ];
    }

    public function getRecentActivity(int $limit = 10): array
    {
        $activities = collect();

        // Real data: Recent Schools
        $recentSchools = School::with('manager')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($school) => [
                'type' => 'school_created',
                'description' => "School '{$school->display_name}' was created",
                'user' => $school->manager?->name ?? 'System',
                'created_at' => $school->created_at,
                'icon' => 'school',
                'color' => 'primary',
            ]);

        $activities = $activities->concat($recentSchools);

        // Real data: Recent Therapists
        $recentTherapists = TherapistProfile::with('manager')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($therapist) => [
                'type' => 'therapist_created',
                'description' => "Therapist '{$therapist->first_name} {$therapist->last_name}' was added",
                'user' => $therapist->manager?->name ?? 'System',
                'created_at' => $therapist->created_at,
                'icon' => 'user',
                'color' => 'success',
            ]);

        $activities = $activities->concat($recentTherapists);

        return $activities
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->toArray();
    }

    public function getUpcomingEvents(): array
    {
        // Dummy data for upcoming events
        return [
            [
                'title' => 'SSA Expiring',
                'entity' => 'John Doe - SLP Services',
                'due_date' => now()->addDays(5),
                'priority' => 'high',
            ],
            [
                'title' => 'Credential Renewal',
                'entity' => 'Jane Smith - OT License',
                'due_date' => now()->addDays(12),
                'priority' => 'medium',
            ],
            [
                'title' => 'Documentation Due',
                'entity' => 'Progress Report - Student ABC',
                'due_date' => now()->addDays(3),
                'priority' => 'high',
            ],
            [
                'title' => 'SSA Review',
                'entity' => 'Quarterly Review - District 5',
                'due_date' => now()->addDays(20),
                'priority' => 'low',
            ],
        ];
    }

    public function getOperationalMetrics(): array
    {
        $totalSchools = School::count();
        $totalTherapists = TherapistProfile::count();

        return [
            [
                'label' => 'School-Therapist Ratio',
                'value' => $totalTherapists > 0 ? number_format($totalSchools / $totalTherapists, 1) . ':1' : 'N/A',
                'trend' => '+0.3',
                'trend_direction' => 'up',
            ],
            [
                'label' => 'Avg SSA Duration',
                'value' => '6.2 months',
                'trend' => '0',
                'trend_direction' => 'neutral',
            ],
            [
                'label' => 'Service Completion Rate',
                'value' => '87%',
                'trend' => '+3%',
                'trend_direction' => 'up',
            ],
            [
                'label' => 'Documentation Compliance',
                'value' => '92%',
                'trend' => '+5%',
                'trend_direction' => 'up',
            ],
        ];
    }

    public function getQuickActions(): array
    {
        return [
            [
                'title' => 'Create New SSA',
                'description' => 'Set up service agreement',
                'route' => '#', // admin.ssas.create when implemented
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
                'route' => '#', // admin.students.create when implemented
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

    private function generateTrendData(int $min, int $max, int $count): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = rand($min, $max);
        }
        return $data;
    }
}
