<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Enums\SSAStatus;
use App\Enums\SubRequestStatus;
use App\Enums\UserStatus;
use App\Models\ScheduleSubRequest;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\ServiceSupportAgreement;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    /** @var array<int, string> */
    private const POSITION_CHART_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

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

    public function getNewSchoolsThisMonth(): int
    {
        return School::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function getTherapistCount(): int
    {
        return User::query()->where('role', Role::THERAPIST)->count();
    }

    public function getActiveTherapistCount(): int
    {
        return User::query()
            ->where('role', Role::THERAPIST)
            ->where('status', UserStatus::ACTIVE)
            ->count();
    }

    public function getInactiveTherapistCount(): int
    {
        return User::query()
            ->where('role', Role::THERAPIST)
            ->where('status', UserStatus::INACTIVE)
            ->count();
    }

    public function getNewTherapistsThisMonth(): int
    {
        return User::query()
            ->where('role', Role::THERAPIST)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function getStudentCount(): int
    {
        return User::query()->where('role', Role::STUDENT)->count();
    }

    public function getActiveStudentCount(): int
    {
        return User::query()
            ->where('role', Role::STUDENT)
            ->where('status', UserStatus::ACTIVE)
            ->count();
    }

    public function getInactiveStudentCount(): int
    {
        return User::query()
            ->where('role', Role::STUDENT)
            ->where('status', UserStatus::INACTIVE)
            ->count();
    }

    public function getNewStudentsThisMonth(): int
    {
        return User::query()
            ->where('role', Role::STUDENT)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function getSSACount(): int
    {
        return ServiceSupportAgreement::count();
    }

    public function getActiveSSACount(): int
    {
        return ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)->count();
    }

    public function getPendingSSACount(): int
    {
        return ServiceSupportAgreement::where('status', SSAStatus::PENDING)->count();
    }

    public function getCompletedSSACount(): int
    {
        return ServiceSupportAgreement::where('status', SSAStatus::COMPLETED)->count();
    }

    public function getSSAsExpiringSoon(int $days = 7): int
    {
        return ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->whereBetween('end_date', [now(), now()->addDays($days)])
            ->count();
    }

    public function getAverageSSAUtilization(): int
    {
        $ssaUtilization = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->where('tho_minutes', '>', 0)
            ->selectRaw('AVG(CASE WHEN tho_minutes > 0 THEN (CAST(served_minutes AS DECIMAL(10,2)) / CAST(tho_minutes AS DECIMAL(10,2))) * 100 ELSE 0 END) as avg_utilization')
            ->value('avg_utilization'); // @phpstan-ignore argument.type

        return $ssaUtilization ? (int) round((float) $ssaUtilization) : 0;
    }

    public function getSchoolsWithoutManagers(): int
    {
        return School::whereNull('manager_id')->count();
    }

    public function getInactiveTherapistsCount(): int
    {
        return TherapistProfile::whereHas('user', function ($query) {
            $query->where('status', '!=', UserStatus::ACTIVE); // @phpstan-ignore argument.type
        })->count();
    }

    public function getActiveStudentsCount(): int
    {
        return User::where('role', Role::STUDENT)
            ->where('status', UserStatus::ACTIVE)
            ->count();
    }

    public function getStudentsWithActiveSSAsCount(): int
    {
        return ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)
            ->distinct('student_id')
            ->count('student_id');
    }

    public function getUnassignedSSAsCount(): int
    {
        return ServiceSupportAgreement::where('status', SSAStatus::PENDING)
            ->whereNull('assigned_therapist_id')
            ->count();
    }

    /**
     * Open sub requests grouped by the original therapist's position, limited to
     * sessions whose combined (schedule_date + start_time) UTC instant is still
     * in the future — coverage that is genuinely pending. Requests for sessions
     * that have already passed are no longer actionable and are excluded.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getOpenSubRequestsByPosition(): array
    {
        $positionExpression = "COALESCE(positions.name, 'Unassigned')";

        $nowUtc = now()->toDateTimeString();

        $rows = ScheduleSubRequest::query()
            ->where('schedule_sub_requests.status', SubRequestStatus::OPEN->value)
            ->join('schedules', 'schedules.id', '=', 'schedule_sub_requests.schedule_id')
            ->whereNull('schedules.deleted_at')
            ->whereRaw('TIMESTAMP(schedules.schedule_date, schedules.start_time) > ?', [$nowUtc])
            ->join('therapist_profiles', 'therapist_profiles.user_id', '=', 'schedules.therapist_id')
            ->leftJoin('positions', 'positions.id', '=', 'therapist_profiles.position_id')
            ->selectRaw($positionExpression.' as position, count(*) as count')
            ->groupBy(DB::raw($positionExpression))
            ->orderBy('position')
            ->get();

        $labels = $rows->pluck('position')->map(static fn ($label): string => (string) $label)->values()->all();
        $palette = self::POSITION_CHART_COLORS;
        $colors = collect($labels)
            ->keys()
            ->map(static fn (int $index): string => $palette[$index % count($palette)])
            ->all();

        return [
            'labels' => $labels,
            'data' => $rows->pluck('count')->map(static fn ($count): int => (int) $count)->values()->all(),
            'colors' => $colors,
        ];
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getPendingSSAs(int $limit = 5): Collection
    {
        return ServiceSupportAgreement::with(['student.studentProfile.school', 'primaryService'])
            ->where('status', SSAStatus::PENDING)
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getExpiringSSAs(int $days = 30, int $limit = 4): Collection
    {
        return ServiceSupportAgreement::with(['student.studentProfile', 'primaryService'])
            ->where('status', SSAStatus::ACTIVE)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('end_date', 'asc')
            ->take($limit)
            ->get();
    }

    /** @return Collection<int, SchoolContract> */
    public function getExpiringSchoolContracts(int $days = 30, int $limit = 4): Collection
    {
        return SchoolContract::with('school')
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('end_date', 'asc')
            ->take($limit)
            ->get();
    }

    public function getTotalTherapistProfilesCount(): int
    {
        return TherapistProfile::count();
    }

    public function getActiveSchoolsCount(): int
    {
        return School::where('status', SchoolStatus::ACTIVE)->count();
    }

    public function getActiveTherapistsByUserStatusCount(): int
    {
        return User::byRole(Role::THERAPIST)
            ->where('status', UserStatus::ACTIVE)
            ->count();
    }

    public function getAverageSSADurationMonths(): string
    {
        $avgSSADuration = ServiceSupportAgreement::whereNotNull('end_date')
            ->whereNotNull('start_date')
            ->selectRaw('AVG(TIMESTAMPDIFF(MONTH, start_date, end_date)) as avg_months')
            ->value('avg_months'); // @phpstan-ignore argument.type

        return $avgSSADuration ? number_format((float) $avgSSADuration, 1) : '0.0';
    }

    public function getServiceCompletionRate(): int
    {
        $totalSSAs = ServiceSupportAgreement::count();
        $completedSSAs = ServiceSupportAgreement::where('status', SSAStatus::COMPLETED)->count();

        return $totalSSAs > 0 ? (int) round(($completedSSAs / $totalSSAs) * 100) : 0;
    }

    public function getActiveSchoolContractsCount(): int
    {
        return SchoolContract::where('status', ContractStatus::ACTIVE)->count();
    }

    public function getActiveTherapistContractsCount(): int
    {
        return TherapistContract::where('status', ContractStatus::ACTIVE)->count();
    }

    public function getTotalContractsCount(): int
    {
        return SchoolContract::count() + TherapistContract::count();
    }
}
