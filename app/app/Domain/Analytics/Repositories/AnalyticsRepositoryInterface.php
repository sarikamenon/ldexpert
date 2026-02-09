<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface AnalyticsRepositoryInterface
{
    public function getSchoolCount(): int;

    public function getActiveSchoolCount(): int;

    public function getInactiveSchoolCount(): int;

    public function getSchoolsByState(): array;

    public function getSchoolsByType(): array;

    public function getSchoolsGrowthTrend(Carbon $startDate, Carbon $endDate): array;

    public function getSchoolsByManager(): array;

    public function getRecentSchoolAdditions(int $limit): Collection;

    public function getTherapistProfileCount(): int;

    public function getActiveTherapistProfileCount(): int;

    public function getTherapistsByPosition(): array;

    public function getTherapistsByEmployeeType(): array;

    public function getTherapistsByState(): array;

    public function getTherapistsGrowthTrend(Carbon $startDate, Carbon $endDate): array;

    public function getRecentTherapistAdditions(int $limit): Collection;

    public function getNewSchoolsInPeriod(Carbon $startDate, Carbon $endDate): int;

    public function getNewTherapistProfilesInPeriod(Carbon $startDate, Carbon $endDate): int;

    public function getUserCount(): int;

    public function getActiveUserCount(): int;

    public function getUsersByRole(): array;

    public function getActivitySummary(Carbon $startDate, Carbon $endDate): array;
}
