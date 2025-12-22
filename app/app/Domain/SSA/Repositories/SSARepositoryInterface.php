<?php

declare(strict_types=1);

namespace App\Domain\SSA\Repositories;

use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\SSAAssignmentDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\UpdateSSADTO;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SSARepositoryInterface
{
    public function paginate(SSAFilterDTO $filters): LengthAwarePaginator;

    public function find(int $id): ?ServiceSupportAgreement;

    public function findWithRelations(int $id, array $relations = []): ?ServiceSupportAgreement;

    public function create(CreateSSADTO $dto): ServiceSupportAgreement;

    public function update(ServiceSupportAgreement $ssa, UpdateSSADTO $dto): ServiceSupportAgreement;

    public function changeStatus(ServiceSupportAgreement $ssa, ChangeSSAStatusDTO $dto): ServiceSupportAgreement;

    public function assignTherapist(ServiceSupportAgreement $ssa, SSAAssignmentDTO $dto): ServiceSupportAgreement;

    public function unassignTherapist(ServiceSupportAgreement $ssa, ?string $reason = null): ServiceSupportAgreement;

    public function getAssignmentHistory(ServiceSupportAgreement $ssa): Collection;

    /**
     * @return array{total:int,pending:int,active:int,completed:int,deactivated:int}
     */
    public function metrics(): array;

    public function checkOverlappingSSAs(int $studentId, int $serviceId, string $startDate, string $endDate, ?int $excludeSsaId = null): Collection;

    public function hasStudentAssignedToTherapist(int $studentId, int $therapistId): bool;

    public function getSSAsForMetrics(int $studentId, int $therapistId): Collection;

    public function getActiveSSAsForTherapist(int $therapistId): Collection;

    public function findSSAForSchedule(int $ssaId, int $therapistId): ?ServiceSupportAgreement;

    public function getSSAsForSchoolMetrics(int $schoolId): Collection;

    public function getSSAsForStudentMetrics(int $studentId): Collection;

    public function getSSAsForStudentSchedule(int $studentId): Collection;

    public function getSSAsForTherapistMetrics(int $therapistId): Collection;

    public function getAssignedSSAsForTherapist(int $therapistId): Collection;

    public function getSSAsForTherapistDashboard(int $therapistId, int $limit = 5): Collection;

    public function countNewStudentsThisMonth(int $therapistId): int;
}
