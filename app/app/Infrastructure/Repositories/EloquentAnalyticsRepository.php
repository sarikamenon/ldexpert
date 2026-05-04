<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Enums\SchoolStatus;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function getSchoolCount(): int
    {
        return School::count();
    }

    public function getActiveSchoolCount(): int
    {
        return School::where('status', SchoolStatus::ACTIVE)->count();
    }

    public function getInactiveSchoolCount(): int
    {
        return School::where('status', SchoolStatus::INACTIVE)->count();
    }

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsByState(): array
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

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsByType(): array
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

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsGrowthTrend(Carbon $startDate, Carbon $endDate): array
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

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsByManager(): array
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

    /** @return Collection<int, array<string, mixed>> */
    public function getRecentSchoolAdditions(int $limit): Collection
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
            });
    }

    public function getTherapistProfileCount(): int
    {
        return TherapistProfile::count();
    }

    public function getActiveTherapistProfileCount(): int
    {
        return TherapistProfile::active()->count();
    }

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByPosition(): array
    {
        $positionExpression = "COALESCE(positions.name, 'Unassigned')";

        $therapists = DB::table('therapist_profiles')
            ->leftJoin('positions', 'therapist_profiles.position_id', '=', 'positions.id')
            ->select(DB::raw($positionExpression.' as position'), DB::raw('count(*) as count'))
            ->whereNull('therapist_profiles.deleted_at')
            ->groupBy(DB::raw($positionExpression))
            ->orderBy('position')
            ->get();

        return [
            'labels' => $therapists->pluck('position')->map(static fn ($label): string => (string) $label)->values()->all(),
            'data' => $therapists->pluck('count')->map(static fn ($count): int => (int) $count)->values()->all(),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByEmployeeType(): array
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

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByState(): array
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

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsGrowthTrend(Carbon $startDate, Carbon $endDate): array
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

    /** @return Collection<int, array<string, mixed>> */
    public function getRecentTherapistAdditions(int $limit): Collection
    {
        $user = auth()->user();

        return TherapistProfile::with(['user', 'position'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->withUserTimezone($user)
            ->map(function ($therapist) {
                return [
                    'id' => $therapist->id,
                    'name' => "{$therapist->first_name} {$therapist->last_name}",
                    'position' => $therapist->position->name ?? 'N/A',
                    'created_at' => $therapist->created_at_local->format('Y-m-d'),
                ];
            });
    }

    public function getNewSchoolsInPeriod(Carbon $startDate, Carbon $endDate): int
    {
        return School::whereBetween('created_at', [$startDate, $endDate])->count();
    }

    public function getNewTherapistProfilesInPeriod(Carbon $startDate, Carbon $endDate): int
    {
        return TherapistProfile::whereBetween('created_at', [$startDate, $endDate])->count();
    }

    public function getUserCount(): int
    {
        return User::count();
    }

    public function getActiveUserCount(): int
    {
        return User::where('status', UserStatus::ACTIVE)->count();
    }

    /** @return array<string, array<int, mixed>> */
    public function getUsersByRole(): array
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
}
