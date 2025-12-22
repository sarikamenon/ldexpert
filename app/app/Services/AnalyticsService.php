<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Analytics\Repositories\AnalyticsRepositoryInterface;
use Carbon\Carbon;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepositoryInterface $repository,
    ) {}

    public function getSchoolsAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return [
            'total' => $this->repository->getSchoolCount(),
            'active' => $this->repository->getActiveSchoolCount(),
            'inactive' => $this->repository->getInactiveSchoolCount(),
            'by_state' => $this->repository->getSchoolsByState(),
            'by_type' => $this->repository->getSchoolsByType(),
            'growth_trend' => $this->repository->getSchoolsGrowthTrend($startDate, $endDate),
            'by_manager' => $this->repository->getSchoolsByManager(),
            'recent_additions' => $this->repository->getRecentSchoolAdditions(10)->toArray(),
        ];
    }

    public function getTherapistsAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return [
            'total' => $this->repository->getTherapistProfileCount(),
            'active' => $this->repository->getActiveTherapistProfileCount(),
            'by_position' => $this->repository->getTherapistsByPosition(),
            'by_employee_type' => $this->repository->getTherapistsByEmployeeType(),
            'by_state' => $this->repository->getTherapistsByState(),
            'growth_trend' => $this->repository->getTherapistsGrowthTrend($startDate, $endDate),
            'recent_additions' => $this->repository->getRecentTherapistAdditions(10)->toArray(),
        ];
    }

    public function getOverallAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return [
            'schools' => [
                'total' => $this->repository->getSchoolCount(),
                'active' => $this->repository->getActiveSchoolCount(),
                'new_this_period' => $this->repository->getNewSchoolsInPeriod($startDate, $endDate),
            ],
            'therapists' => [
                'total' => $this->repository->getTherapistProfileCount(),
                'active' => $this->repository->getActiveTherapistProfileCount(),
                'new_this_period' => $this->repository->getNewTherapistProfilesInPeriod($startDate, $endDate),
            ],
            'users' => [
                'total' => $this->repository->getUserCount(),
                'active' => $this->repository->getActiveUserCount(),
                'by_role' => $this->repository->getUsersByRole(),
            ],
            'activity_summary' => $this->repository->getActivitySummary($startDate, $endDate),
        ];
    }
}
