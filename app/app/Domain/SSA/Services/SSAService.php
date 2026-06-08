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
use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;
use App\Exceptions\ContractOverlapException;
use App\Models\School;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class SSAService
{
    public function __construct(
        private readonly SSARepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<int, ServiceSupportAgreement> */
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
        // COMPLETED is always terminal — no transitions allowed
        if ($ssa->status === SSAStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Cannot change status of a completed SSA.',
            ]);
        }

        // DEACTIVATED can only transition to ACTIVE (reactivation)
        if ($ssa->status === SSAStatus::DEACTIVATED && $dto->status !== SSAStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'A deactivated SSA can only be reactivated.',
            ]);
        }

        // Cannot activate without assigned therapist
        if ($dto->status === SSAStatus::ACTIVE && $ssa->assigned_therapist_id === null) {
            throw ValidationException::withMessages([
                'status' => 'Cannot activate SSA without an assigned therapist.',
            ]);
        }

        $rules = [
            SSAStatus::COMPLETED->value   => [SSAStatus::ACTIVE],
            SSAStatus::DEACTIVATED->value => [SSAStatus::ACTIVE, SSAStatus::PENDING],
        ];

        $allowedStatuses = $rules[$dto->status->value] ?? null;

        if ($allowedStatuses !== null && !in_array($ssa->status, $allowedStatuses, true)) {
            $action = ucfirst($dto->status->value);
            $allowedLabels = implode(' or ', array_map(fn($s) => $s->label(), $allowedStatuses));

            throw ValidationException::withMessages([
                'status' => "Can only {$action} a {$allowedLabels} SSA.",
            ]);
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

    /**
     * @return array<string, mixed>
     */
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
        $frequencyEnum = ServiceFrequency::from($frequency);
        $numberOfFrequencies = $frequencyEnum->occurrencesInDateRange($start, $end);
        $normalizedSessions = $frequencyEnum->normalizeSessionsPerFrequency($sessionsPerFrequency) ?? 0;
        $totalSessions = $numberOfFrequencies * $normalizedSessions;

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

    /** @return EloquentCollection<int, ServiceSupportAgreement> */
    public function getActiveSSAsForTherapist(int $therapistId): EloquentCollection
    {
        return $this->repository->getActiveSSAsForTherapist($therapistId);
    }

    /**
     * Returns unique, sorted students across all active SSAs for a therapist.
     *
     * @return Collection<int, User>
     */
    public function getUniqueStudentsForTherapist(int $therapistId): Collection
    {
        return $this->getActiveSSAsForTherapist($therapistId)
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Schools that have at least one active SSA assigned to this therapist,
     * ordered by display name (falling back to full name).
     *
     * @return Collection<int, School>
     */
    public function getSchoolsForTherapist(int $therapistId): Collection
    {
        $studentIds = $this->getActiveSSAsForTherapist($therapistId)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($studentIds)) {
            return collect();
        }

        $schoolIds = StudentProfile::query()
            ->forUserIds($studentIds)
            ->withSchool()
            ->pluck('school_id')
            ->unique()
            ->values()
            ->all();

        if (empty($schoolIds)) {
            return collect();
        }

        return School::query()
            ->whereIn('id', $schoolIds)
            ->orderedByDisplayName()
            ->get();
    }

    public function therapistHasAccessToSchool(int $therapistId, int $schoolId): bool
    {
        return $this->getSchoolsForTherapist($therapistId)
            ->contains(static fn (School $school): bool => $school->id === $schoolId);
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
