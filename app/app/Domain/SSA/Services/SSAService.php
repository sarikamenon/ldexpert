<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SSAAssignmentDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\UpdateSSADTO;
use App\Enums\SSAStatus;
use App\Exceptions\ContractOverlapException;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class SSAService
{
    public function __construct(
        private readonly SSARepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<ServiceSupportAgreement> */
    public function paginate(SSAFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:\Illuminate\Support\Collection<int,ServiceSupportAgreement>}
     */
    public function listForDataTables(SSAFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    public function find(int $id): ?ServiceSupportAgreement
    {
        return $this->repository->find($id);
    }

    public function create(CreateSSADTO $dto): ServiceSupportAgreement
    {
        // Check for overlapping SSAs
        $overlapping = $this->repository->checkOverlappingSSAs(
            $dto->studentId,
            $dto->primaryServiceId,
            $dto->startDate,
            $dto->endDate
        );

        if ($overlapping->isNotEmpty()) {
            throw new ContractOverlapException(
                'An active or pending SSA already exists for this student and service within the specified date range.'
            );
        }

        return $this->repository->create($dto);
    }

    public function update(ServiceSupportAgreement $ssa, UpdateSSADTO $dto): ServiceSupportAgreement
    {
        $startDate = $dto->startDate ?? $ssa->start_date->format('Y-m-d');
        $endDate = $dto->endDate ?? $ssa->end_date?->format('Y-m-d');

        // Check for overlapping SSAs (excluding current SSA)
        if ($dto->startDate || $dto->endDate) {
            $overlapping = $this->repository->checkOverlappingSSAs(
                $ssa->student_id,
                $ssa->primary_service_id,
                $startDate,
                $endDate,
                $ssa->id
            );

            if ($overlapping->isNotEmpty()) {
                throw new ContractOverlapException(
                    'An active or pending SSA already exists for this student and service within the specified date range.'
                );
            }
        }

        return $this->repository->update($ssa, $dto);
    }

    public function changeStatus(ServiceSupportAgreement $ssa, ChangeSSAStatusDTO $dto): ServiceSupportAgreement
    {
        // Cannot change status if already completed or deactivated
        if (in_array($ssa->status, [SSAStatus::COMPLETED, SSAStatus::DEACTIVATED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Cannot change status of a completed or deactivated SSA.',
            ]);
        }

        // Cannot activate without assigned therapist
        if ($dto->status === SSAStatus::ACTIVE && $ssa->assigned_therapist_id === null) {
            throw ValidationException::withMessages([
                'status' => 'Cannot activate SSA without an assigned therapist.',
            ]);
        }

        // Can only complete or deactivate from ACTIVE status
        if (in_array($dto->status, [SSAStatus::COMPLETED, SSAStatus::DEACTIVATED], true)) {
            if ($ssa->status !== SSAStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'status' => 'Can only complete or deactivate an active SSA.',
                ]);
            }
        }

        return $this->repository->changeStatus($ssa, $dto);
    }

    public function assignTherapist(ServiceSupportAgreement $ssa, SSAAssignmentDTO $dto): ServiceSupportAgreement
    {
        return $this->repository->assignTherapist($ssa, $dto);
    }

    public function unassignTherapist(ServiceSupportAgreement $ssa, ?string $reason = null): ServiceSupportAgreement
    {
        return $this->repository->unassignTherapist($ssa, $reason);
    }

    /** @return Collection<int, \App\Models\SSAAssignmentHistory> */
    public function getAssignmentHistory(ServiceSupportAgreement $ssa): Collection
    {
        return $this->repository->getAssignmentHistory($ssa);
    }

    public function metrics(): array
    {
        return $this->repository->metrics();
    }

    public function calculateThoMinutes(
        int $minutesPerSession,
        string $frequency,
        int $sessionsPerFrequency,
        string $startDate,
        string $endDate
    ): int {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $daysDiff = $start->diffInDays($end) + 1;

        $frequencyMultiplier = match ($frequency) {
            'weekly' => 52 / 365,
            'bi_weekly' => 26 / 365,
            'monthly' => 12 / 365,
            'quarterly' => 4 / 365,
            default => 0,
        };

        $numberOfFrequencies = (int) ceil($daysDiff * $frequencyMultiplier);
        $totalSessions = $numberOfFrequencies * $sessionsPerFrequency;

        return $totalSessions * $minutesPerSession;
    }

    public function hasStudentAssignedToTherapist(int $studentId, int $therapistId): bool
    {
        return $this->repository->hasStudentAssignedToTherapist($studentId, $therapistId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForMetrics(int $studentId, int $therapistId): Collection
    {
        return $this->repository->getSSAsForMetrics($studentId, $therapistId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getActiveSSAsForTherapist(int $therapistId): Collection
    {
        return $this->repository->getActiveSSAsForTherapist($therapistId);
    }

    public function findSSAForSchedule(int $ssaId, int $therapistId): ?ServiceSupportAgreement
    {
        return $this->repository->findSSAForSchedule($ssaId, $therapistId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForSchoolMetrics(int $schoolId): Collection
    {
        return $this->repository->getSSAsForSchoolMetrics($schoolId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForStudentMetrics(int $studentId): Collection
    {
        return $this->repository->getSSAsForStudentMetrics($studentId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForStudentSchedule(int $studentId): Collection
    {
        return $this->repository->getSSAsForStudentSchedule($studentId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForTherapistMetrics(int $therapistId): Collection
    {
        return $this->repository->getSSAsForTherapistMetrics($therapistId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getAssignedSSAsForTherapist(int $therapistId): Collection
    {
        return $this->repository->getAssignedSSAsForTherapist($therapistId);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForTherapistDashboard(int $therapistId, int $limit = 5): Collection
    {
        return $this->repository->getSSAsForTherapistDashboard($therapistId, $limit);
    }

    public function countNewStudentsThisMonth(int $therapistId): int
    {
        return $this->repository->countNewStudentsThisMonth($therapistId);
    }
}
