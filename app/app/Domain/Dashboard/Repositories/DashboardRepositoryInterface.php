<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Repositories;

use App\Models\SchoolContract;
use App\Models\ServiceSupportAgreement;
use Illuminate\Support\Collection;

interface DashboardRepositoryInterface
{
    public function getSchoolCount(): int;

    public function getActiveSchoolCount(): int;

    public function getInactiveSchoolCount(): int;

    public function getNewSchoolsThisMonth(): int;

    public function getTherapistCount(): int;

    public function getActiveTherapistCount(): int;

    public function getInactiveTherapistCount(): int;

    public function getNewTherapistsThisMonth(): int;

    public function getStudentCount(): int;

    public function getActiveStudentCount(): int;

    public function getInactiveStudentCount(): int;

    public function getNewStudentsThisMonth(): int;

    public function getSSACount(): int;

    public function getActiveSSACount(): int;

    public function getPendingSSACount(): int;

    public function getCompletedSSACount(): int;

    public function getSSAsExpiringSoon(int $days = 7): int;

    public function getAverageSSAUtilization(): int;

    public function getSchoolsWithoutManagers(): int;

    public function getInactiveTherapistsCount(): int;

    public function getActiveStudentsCount(): int;

    public function getStudentsWithActiveSSAsCount(): int;

    public function getUnassignedSSAsCount(): int;

    /** @return Collection<string, ServiceSupportAgreement> */
    public function getSSAStatusDistribution(): Collection;

    /** @return array<string, array<int, mixed>> */
    public function getTherapistsByPosition(): array;

    /** @return array<string, array<int, mixed>> */
    public function getUtilizationTrendData(): array;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getExpiringSSAs(int $days = 30, int $limit = 4): Collection;

    /** @return Collection<int, SchoolContract> */
    public function getExpiringSchoolContracts(int $days = 30, int $limit = 4): Collection;

    public function getTotalTherapistProfilesCount(): int;

    public function getActiveSchoolsCount(): int;

    public function getActiveTherapistsByUserStatusCount(): int;

    public function getAverageSSADurationMonths(): string;

    public function getServiceCompletionRate(): int;

    public function getActiveSchoolContractsCount(): int;

    public function getActiveTherapistContractsCount(): int;

    public function getTotalContractsCount(): int;
}
