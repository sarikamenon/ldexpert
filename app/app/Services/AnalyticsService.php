<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SchoolStatus;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getSchoolsAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return [
            'total' => School::count(),
            'active' => School::where('status', SchoolStatus::ACTIVE)->count(),
            'inactive' => School::where('status', SchoolStatus::INACTIVE)->count(),
            'by_state' => $this->getSchoolsByState(),
            'by_type' => $this->getSchoolsByType(),
            'growth_trend' => $this->getSchoolsGrowthTrend($startDate, $endDate),
            'by_manager' => $this->getSchoolsByManager(),
            'recent_additions' => $this->getRecentSchoolAdditions(10),
        ];
    }

    public function getTherapistsAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return [
            'total' => TherapistProfile::count(),
            'active' => TherapistProfile::active()->count(),
            'by_position' => $this->getTherapistsByPosition(),
            'by_employee_type' => $this->getTherapistsByEmployeeType(),
            'by_state' => $this->getTherapistsByState(),
            'growth_trend' => $this->getTherapistsGrowthTrend($startDate, $endDate),
            'recent_additions' => $this->getRecentTherapistAdditions(10),
        ];
    }

    public function getOverallAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return [
            'schools' => [
                'total' => School::count(),
                'active' => School::where('status', SchoolStatus::ACTIVE)->count(),
                'new_this_period' => School::whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
            'therapists' => [
                'total' => TherapistProfile::count(),
                'active' => TherapistProfile::active()->count(),
                'new_this_period' => TherapistProfile::whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', UserStatus::ACTIVE)->count(),
                'by_role' => $this->getUsersByRole(),
            ],
            'activity_summary' => $this->getActivitySummary($startDate, $endDate),
        ];
    }

    private function getSchoolsByState(): array
    {
        $schools = DB::table('schools')
            ->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $schools->pluck('state')->toArray(),
            'data' => $schools->pluck('count')->toArray(),
        ];
    }

    private function getSchoolsByType(): array
    {
        $schools = DB::table('schools')
            ->select('school_type', DB::raw('count(*) as count'))
            ->groupBy('school_type')
            ->get();

        return [
            'labels' => $schools->pluck('school_type')->toArray(),
            'data' => $schools->pluck('count')->toArray(),
        ];
    }

    private function getSchoolsGrowthTrend(Carbon $startDate, Carbon $endDate): array
    {
        $schools = DB::table('schools')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $schools->pluck('date')->map(fn ($date) => Carbon::parse($date)->format('M d'))->toArray(),
            'data' => $schools->pluck('count')->toArray(),
        ];
    }

    private function getSchoolsByManager(): array
    {
        $schools = DB::table('schools')
            ->join('users', 'schools.manager_id', '=', 'users.id')
            ->select('users.name as manager', DB::raw('count(*) as count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $schools->pluck('manager')->toArray(),
            'data' => $schools->pluck('count')->toArray(),
        ];
    }

    private function getRecentSchoolAdditions(int $limit): array
    {
        $user = auth()->user();

        return School::with('manager')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->withUserTimezone($user)
            ->map(function ($school) {
                return [
                    'id' => $school->id,
                    'name' => $school->display_name,
                    'manager' => $school->manager?->name,
                    'created_at' => $school->created_at_local->format('Y-m-d'),
                ];
            })
            ->toArray();
    }

    private function getTherapistsByPosition(): array
    {
        $therapists = DB::table('therapist_profiles')
            ->select('position', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('position')
            ->get();

        return [
            'labels' => $therapists->pluck('position')->toArray(),
            'data' => $therapists->pluck('count')->toArray(),
        ];
    }

    private function getTherapistsByEmployeeType(): array
    {
        $therapists = DB::table('therapist_profiles')
            ->select('employee_type', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('employee_type')
            ->get();

        return [
            'labels' => $therapists->pluck('employee_type')->toArray(),
            'data' => $therapists->pluck('count')->toArray(),
        ];
    }

    private function getTherapistsByState(): array
    {
        $therapists = DB::table('therapist_profiles')
            ->select('state', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->whereNotNull('state')
            ->groupBy('state')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $therapists->pluck('state')->toArray(),
            'data' => $therapists->pluck('count')->toArray(),
        ];
    }

    private function getTherapistsGrowthTrend(Carbon $startDate, Carbon $endDate): array
    {
        $therapists = DB::table('therapist_profiles')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $therapists->pluck('date')->map(fn ($date) => Carbon::parse($date)->format('M d'))->toArray(),
            'data' => $therapists->pluck('count')->toArray(),
        ];
    }

    private function getRecentTherapistAdditions(int $limit): array
    {
        $user = auth()->user();

        return TherapistProfile::with('user')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->withUserTimezone($user)
            ->map(function ($therapist) {
                return [
                    'id' => $therapist->id,
                    'name' => "{$therapist->first_name} {$therapist->last_name}",
                    'position' => $therapist->position?->value ?? 'N/A',
                    'created_at' => $therapist->created_at_local->format('Y-m-d'),
                ];
            })
            ->toArray();
    }

    private function getUsersByRole(): array
    {
        $users = DB::table('users')
            ->select('role', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('role')
            ->get();

        return [
            'labels' => $users->pluck('role')->toArray(),
            'data' => $users->pluck('count')->toArray(),
        ];
    }

    private function getActivitySummary(Carbon $startDate, Carbon $endDate): array
    {
        $activities = DB::table('activity_logs')
            ->select('action', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $activities->pluck('action')->map(fn ($action) => ucfirst(str_replace('_', ' ', $action)))->toArray(),
            'data' => $activities->pluck('count')->toArray(),
        ];
    }
}
