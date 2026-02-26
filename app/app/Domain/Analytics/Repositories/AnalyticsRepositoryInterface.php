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

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsByState(): array;

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsByType(): array;

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsGrowthTrend(Carbon $startDate, Carbon $endDate): array;

    /** @return array<string, array<int, mixed>> */
    public function getSchoolsByManager(): array;

    /** @return Collection<int, array<string, mixed>> */
    public function getRecentSchoolAdditions(int $limit): Collection;

    public function getTherapistProfileCount(): int;

    public function getActiveTherapistProfileCount(): int;

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByPosition(): array;

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByEmployeeType(): array;

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByState(): array;

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsGrowthTrend(Carbon $startDate, Carbon $endDate): array;

    /** @return Collection<int, array<string, mixed>> */
    public function getRecentTherapistAdditions(int $limit): Collection;

    public function getNewSchoolsInPeriod(Carbon $startDate, Carbon $endDate): int;

    public function getNewTherapistProfilesInPeriod(Carbon $startDate, Carbon $endDate): int;

    public function getUserCount(): int;

    public function getActiveUserCount(): int;

    /** @return array<string, array<int, mixed>> */
    public function getUsersByRole(): array;

    /** @return array<string, array<int, mixed>> */
    public function getActivitySummary(Carbon $startDate, Carbon $endDate): array;
}
