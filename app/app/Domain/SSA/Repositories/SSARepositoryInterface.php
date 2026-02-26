<?php

declare(strict_types=1);

namespace App\Domain\SSA\Repositories;

use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SSAAssignmentDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\SSAReport\CaseloadReportFilterDTO;
use App\DTOs\SSAReport\ExpirationReportFilterDTO;
use App\DTOs\SSAReport\UtilizationReportFilterDTO;
use App\DTOs\UpdateSSADTO;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface SSARepositoryInterface
{
    /** @return LengthAwarePaginator<int, ServiceSupportAgreement> */
    public function paginate(SSAFilterDTO $filters): LengthAwarePaginator;

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:Collection<int,ServiceSupportAgreement>}
     */
    public function listForDataTables(SSAFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function find(int $id): ?ServiceSupportAgreement;

    /** @param array<int, string> $relations */
    public function findWithRelations(int $id, array $relations = []): ?ServiceSupportAgreement;

    public function create(CreateSSADTO $dto): ServiceSupportAgreement;

    public function update(ServiceSupportAgreement $ssa, UpdateSSADTO $dto): ServiceSupportAgreement;

    public function changeStatus(ServiceSupportAgreement $ssa, ChangeSSAStatusDTO $dto): ServiceSupportAgreement;

    public function assignTherapist(ServiceSupportAgreement $ssa, SSAAssignmentDTO $dto): ServiceSupportAgreement;

    public function unassignTherapist(ServiceSupportAgreement $ssa, ?string $reason = null): ServiceSupportAgreement;

    /** @return Collection<int, \App\Models\SSAAssignmentHistory> */
    public function getAssignmentHistory(ServiceSupportAgreement $ssa): Collection;

    /**
     * @return array{total:int,pending:int,active:int,completed:int,deactivated:int}
     */
    public function metrics(): array;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function checkOverlappingSSAs(int $studentId, int $serviceId, string $startDate, ?string $endDate, ?int $excludeSsaId = null): Collection;

    public function hasStudentAssignedToTherapist(int $studentId, int $therapistId): bool;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForMetrics(int $studentId, int $therapistId): Collection;

    /** @return EloquentCollection<int, ServiceSupportAgreement> */
    public function getActiveSSAsForTherapist(int $therapistId): EloquentCollection;

    public function findSSAForSchedule(int $ssaId, int $therapistId): ?ServiceSupportAgreement;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForSchoolMetrics(int $schoolId): Collection;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForStudentMetrics(int $studentId): Collection;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForStudentSchedule(int $studentId): Collection;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForTherapistMetrics(int $therapistId): Collection;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getAssignedSSAsForTherapist(int $therapistId): Collection;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForTherapistDashboard(int $therapistId, int $limit = 5): Collection;

    public function countNewStudentsThisMonth(int $therapistId): int;

    /** @return LengthAwarePaginator<int, ServiceSupportAgreement> */
    public function getUtilizationReport(UtilizationReportFilterDTO $filters): LengthAwarePaginator;

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getCaseloadReport(CaseloadReportFilterDTO $filters): Collection;

    /**
     * @return array{upcoming: Collection<int, ServiceSupportAgreement>, expired: Collection<int, ServiceSupportAgreement>, pending: Collection<int, ServiceSupportAgreement>, no_current: Collection<int, mixed>}
     */
    public function getExpirationReport(ExpirationReportFilterDTO $filters): array;
}
