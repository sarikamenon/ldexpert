<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SSAAssignmentDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\SSAReport\CaseloadReportFilterDTO;
use App\DTOs\SSAReport\ExpirationReportFilterDTO;
use App\DTOs\SSAReport\UtilizationReportFilterDTO;
use App\DTOs\UpdateSSADTO;
use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAAssignmentHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class EloquentSSARepository implements SSARepositoryInterface
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /** @return LengthAwarePaginator<int, ServiceSupportAgreement> */
    public function paginate(SSAFilterDTO $filters): LengthAwarePaginator
    {
        return $this->applyFilters(ServiceSupportAgreement::query(), $filters)
            ->with([
                'student',
                'student.studentProfile.school',
                'primaryService',
                'assignedTherapist',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    public function listForDataTables(SSAFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters(ServiceSupportAgreement::query(), $filters)
            ->leftJoin('users as students', 'service_support_agreements.student_id', '=', 'students.id')
            ->leftJoin('users as therapists', 'service_support_agreements.assigned_therapist_id', '=', 'therapists.id')
            ->select('service_support_agreements.*')
            ->with([
                'student',
                'student.studentProfile.school',
                'primaryService',
                'assignedTherapist',
                'scheduledSchedules',
            ]);

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('service_support_agreements.id');

        if ($params->searchValue) {
            $search = '%'.$params->searchValue.'%';
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->whereHas('student', function (Builder $studentQuery) use ($search) {
                    $studentQuery->where('name', 'like', $search); // @phpstan-ignore argument.type
                })->orWhereHas('primaryService', function (Builder $serviceQuery) use ($search) {
                    $serviceQuery->where('name', 'like', $search); // @phpstan-ignore argument.type
                });
            });
        }

        $recordsFiltered = (clone $baseQuery)->count('service_support_agreements.id');

        $orderColumn = $params->orderColumn ?? 'service_support_agreements.id';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';

        $baseQuery->orderBy($orderColumn, $orderDir);

        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function find(int $id): ?ServiceSupportAgreement
    {
        return ServiceSupportAgreement::with([
            'student',
            'student.studentProfile.school',
            'primaryService',
            'assignedTherapist',
            'assignmentHistory.therapist',
            'assignmentHistory.assignedBy',
        ])->find($id);
    }

    /** @param array<int, string> $relations */
    public function findWithRelations(int $id, array $relations = []): ?ServiceSupportAgreement
    {
        $query = ServiceSupportAgreement::query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    public function create(CreateSSADTO $dto): ServiceSupportAgreement
    {
        return DB::transaction(function () use ($dto) {
            $data = $dto->toArray();

            // If therapist is assigned during creation, automatically activate
            if ($dto->assignedTherapistId !== null) {
                $data['status'] = SSAStatus::ACTIVE->value;
            }

            $ssa = ServiceSupportAgreement::create($data);

            $this->syncSsaServices($ssa);

            // If therapist is assigned during creation, log it
            if ($dto->assignedTherapistId !== null) {
                SSAAssignmentHistory::create([
                    'ssa_id' => $ssa->id,
                    'therapist_id' => $dto->assignedTherapistId,
                    'action' => 'assigned',
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                ]);
            }

            /** @var ServiceSupportAgreement $freshSsa */
            $freshSsa = $ssa->fresh(['services']);

            return $freshSsa;
        });
    }

    public function update(ServiceSupportAgreement $ssa, UpdateSSADTO $dto): ServiceSupportAgreement
    {
        return DB::transaction(function () use ($ssa, $dto) {
            $data = $dto->toArray();

            // Delegate therapist transitions to assignTherapist()/unassignTherapist() so
            // history rows and status syncing stay in one place.
            if (array_key_exists('assigned_therapist_id', $data)) {
                $newTherapistId = $data['assigned_therapist_id'];
                unset($data['assigned_therapist_id']);

                if ($ssa->assigned_therapist_id !== $newTherapistId) {
                    if ($newTherapistId === null) {
                        $this->unassignTherapist($ssa);
                    } else {
                        $this->assignTherapist($ssa, new SSAAssignmentDTO(therapistId: $newTherapistId, reason: null));
                    }
                    $ssa->refresh();
                }
            }

            if ($data !== []) {
                $ssa->update($data);
            }

            $this->syncSsaServices($ssa);

            /** @var ServiceSupportAgreement $freshSsa */
            $freshSsa = $ssa->fresh(['services']);

            return $freshSsa;
        });
    }

    public function changeStatus(ServiceSupportAgreement $ssa, ChangeSSAStatusDTO $dto): ServiceSupportAgreement
    {
        $ssa->update([
            'status' => $dto->status->value,
        ]);

        /** @var ServiceSupportAgreement $freshSsa */
        $freshSsa = $ssa->fresh();

        return $freshSsa;
    }

    public function assignTherapist(ServiceSupportAgreement $ssa, SSAAssignmentDTO $dto): ServiceSupportAgreement
    {
        return DB::transaction(function () use ($ssa, $dto) {
            $previousTherapistId = $ssa->assigned_therapist_id;

            // If there was a previous therapist, log unassignment
            if ($previousTherapistId !== null) {
                SSAAssignmentHistory::create([
                    'ssa_id' => $ssa->id,
                    'therapist_id' => $previousTherapistId,
                    'action' => 'unassigned',
                    'assigned_by' => Auth::id(),
                    'reason' => 'Reassigned to another therapist',
                    'unassigned_at' => now(),
                ]);
            }

            // Update SSA with new therapist and automatically activate if pending
            $updateData = [
                'assigned_therapist_id' => $dto->therapistId,
            ];

            // Automatically change status to ACTIVE if currently PENDING
            if ($ssa->status === SSAStatus::PENDING) {
                $updateData['status'] = SSAStatus::ACTIVE->value;
            }

            $ssa->update($updateData);

            // Log new assignment
            SSAAssignmentHistory::create([
                'ssa_id' => $ssa->id,
                'therapist_id' => $dto->therapistId,
                'action' => 'assigned',
                'assigned_by' => Auth::id(),
                'reason' => $dto->reason,
                'assigned_at' => now(),
            ]);

            /** @var ServiceSupportAgreement $freshSsa */
            $freshSsa = $ssa->fresh();

            return $freshSsa;
        });
    }

    public function unassignTherapist(ServiceSupportAgreement $ssa, ?string $reason = null): ServiceSupportAgreement
    {
        return DB::transaction(function () use ($ssa, $reason) {
            $therapistId = $ssa->assigned_therapist_id;

            if ($therapistId !== null) {
                // Log unassignment
                SSAAssignmentHistory::create([
                    'ssa_id' => $ssa->id,
                    'therapist_id' => $therapistId,
                    'action' => 'unassigned',
                    'assigned_by' => Auth::id(),
                    'reason' => $reason,
                    'unassigned_at' => now(),
                ]);

                // Update SSA
                $ssa->update([
                    'assigned_therapist_id' => null,
                    'status' => SSAStatus::PENDING->value, // Reset to pending when unassigned
                ]);
            }

            /** @var ServiceSupportAgreement $freshSsa */
            $freshSsa = $ssa->fresh();

            return $freshSsa;
        });
    }

    public function deactivateWithUnassign(ServiceSupportAgreement $ssa, ?string $reason = null): ServiceSupportAgreement
    {
        return DB::transaction(function () use ($ssa, $reason) {
            $therapistId = $ssa->assigned_therapist_id;

            if ($therapistId !== null) {
                SSAAssignmentHistory::create([
                    'ssa_id' => $ssa->id,
                    'therapist_id' => $therapistId,
                    'action' => 'unassigned',
                    'assigned_by' => Auth::id(),
                    'reason' => $reason,
                    'unassigned_at' => now(),
                ]);
            }

            $ssa->update([
                'assigned_therapist_id' => null,
                'status' => SSAStatus::DEACTIVATED->value,
            ]);

            /** @var ServiceSupportAgreement $freshSsa */
            $freshSsa = $ssa->fresh();

            return $freshSsa;
        });
    }

    /** @return Collection<int, SSAAssignmentHistory> */
    public function getAssignmentHistory(ServiceSupportAgreement $ssa): Collection
    {
        return SSAAssignmentHistory::where('ssa_id', $ssa->id)
            ->with(['therapist', 'assignedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function metrics(): array
    {
        $total = ServiceSupportAgreement::count();
        $pending = ServiceSupportAgreement::where('status', SSAStatus::PENDING)->count();
        $active = ServiceSupportAgreement::where('status', SSAStatus::ACTIVE)->count();
        $completed = ServiceSupportAgreement::where('status', SSAStatus::COMPLETED)->count();
        $deactivated = ServiceSupportAgreement::where('status', SSAStatus::DEACTIVATED)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'active' => $active,
            'completed' => $completed,
            'deactivated' => $deactivated,
        ];
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function checkOverlappingSSAs(int $studentId, int $serviceId, string $startDate, ?string $endDate, ?int $excludeSsaId = null): Collection
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $query = ServiceSupportAgreement::where('student_id', $studentId)
            ->where('primary_service_id', $serviceId)
            ->whereIn('status', [SSAStatus::PENDING->value, SSAStatus::ACTIVE->value])
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                    ->orWhere(function (Builder $subQ) use ($start, $end) {
                        $subQ->where('start_date', '<=', $start->format('Y-m-d'))
                            ->where('end_date', '>=', $end->format('Y-m-d'));
                    });
            });

        if ($excludeSsaId !== null) {
            $query->where('id', '!=', $excludeSsaId);
        }

        return $query->get();
    }

    public function hasStudentAssignedToTherapist(int $studentId, int $therapistId): bool
    {
        return ServiceSupportAgreement::where('student_id', $studentId)
            ->where('assigned_therapist_id', $therapistId)
            ->exists();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForMetrics(int $studentId, int $therapistId): Collection
    {
        return ServiceSupportAgreement::with(['primaryService', 'assignedTherapist', 'scheduledSchedules'])
            ->where('student_id', $studentId)
            ->where('assigned_therapist_id', $therapistId)
            ->get();
    }

    /** @return EloquentCollection<int, ServiceSupportAgreement> */
    public function getActiveSSAsForTherapist(int $therapistId): EloquentCollection
    {
        return ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapistId)
            ->where('status', SSAStatus::ACTIVE)
            ->with(['student', 'primaryService'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findSSAForSchedule(int $ssaId, int $therapistId): ?ServiceSupportAgreement
    {
        return ServiceSupportAgreement::query()
            ->where('id', $ssaId)
            ->where('assigned_therapist_id', $therapistId)
            ->where('status', SSAStatus::ACTIVE)
            ->with(['student', 'student.studentProfile.school', 'primaryService', 'services'])
            ->first();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForSchoolMetrics(int $schoolId): Collection
    {
        return ServiceSupportAgreement::with(['student', 'primaryService', 'assignedTherapist'])
            ->whereHas('student.studentProfile', fn ($q) => $q->where('school_id', $schoolId)) // @phpstan-ignore argument.type
            ->get();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForStudentMetrics(int $studentId): Collection
    {
        return ServiceSupportAgreement::with(['primaryService', 'assignedTherapist', 'scheduledSchedules'])
            ->where('student_id', $studentId)
            ->get();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForStudentSchedule(int $studentId): Collection
    {
        return ServiceSupportAgreement::query()
            ->where('student_id', $studentId)
            ->with(['primaryService', 'assignedTherapist'])
            ->get(['id', 'student_id', 'assigned_therapist_id', 'primary_service_id', 'status']);
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForTherapistMetrics(int $therapistId): Collection
    {
        return ServiceSupportAgreement::with(['student', 'primaryService'])
            ->where('assigned_therapist_id', $therapistId)
            ->get();
    }

    /**
     * @param  Builder<ServiceSupportAgreement>  $query
     * @return Builder<ServiceSupportAgreement>
     */
    private function applyFilters(Builder $query, SSAFilterDTO $filters): Builder
    {
        if ($filters->search) {
            $query->searchByName($filters->search);
        }

        if ($filters->statuses !== null && $filters->statuses !== []) {
            $query->withStatuses($filters->statuses);
        } elseif ($filters->status) {
            $query->withStatus($filters->status);
        }

        if ($filters->studentId) {
            $query->forStudent($filters->studentId);
        }

        if ($filters->serviceId) {
            $query->forPrimaryService($filters->serviceId);
        }

        if ($filters->therapistId) {
            $query->forAssignedTherapist($filters->therapistId);
        }

        if ($filters->schoolId) {
            $query->forSchool($filters->schoolId);
        }

        return $query;
    }

    private function syncSsaServices(ServiceSupportAgreement $ssa): void
    {
        /** @var array<int, int> $oldIds */
        $oldIds = $ssa->services()->pluck('services.id')->all();

        $ssa->services()->sync([
            (int) $ssa->primary_service_id => ['is_primary' => true],
        ]);

        /** @var array<int, int> $newIds */
        $newIds = $ssa->services()->pluck('services.id')->all();

        sort($oldIds);
        sort($newIds);

        if ($oldIds === $newIds) {
            return;
        }

        $this->auditRecorder->record(
            auditable: $ssa,
            event: 'services_synced',
            oldValues: ['service_ids' => $oldIds],
            newValues: ['service_ids' => $newIds],
        );
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getAssignedSSAsForTherapist(int $therapistId): Collection
    {
        return ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapistId)
            ->get();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getSSAsForTherapistDashboard(int $therapistId, int $limit = 5): Collection
    {
        return ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapistId)
            ->with(['student.studentProfile.school', 'primaryService'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function countNewStudentsThisMonth(int $therapistId): int
    {
        return ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapistId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->distinct()
            ->count('student_id');
    }

    /** @return LengthAwarePaginator<int, ServiceSupportAgreement> */
    public function getUtilizationReport(UtilizationReportFilterDTO $filters): LengthAwarePaginator
    {
        $query = ServiceSupportAgreement::query()
            ->with([
                'student',
                'student.studentProfile.school',
                'primaryService',
                'assignedTherapist',
            ]);

        if ($filters->startDate) {
            $query->where(function (Builder $q) use ($filters) {
                $q->whereBetween('start_date', [$filters->startDate->format('Y-m-d'), $filters->endDate?->format('Y-m-d') ?? '9999-12-31'])
                    ->orWhereBetween('end_date', [$filters->startDate->format('Y-m-d'), $filters->endDate?->format('Y-m-d') ?? '9999-12-31'])
                    ->orWhere(function (Builder $subQ) use ($filters) {
                        $subQ->where('start_date', '<=', $filters->startDate->format('Y-m-d'))
                            ->where('end_date', '>=', $filters->endDate?->format('Y-m-d') ?? '9999-12-31');
                    });
            });
        }

        if ($filters->endDate) {
            $query->where('end_date', '<=', $filters->endDate->format('Y-m-d'));
        }

        if ($filters->schoolIds) {
            $query->whereHas('student.studentProfile', function (Builder $q) use ($filters) {
                $q->whereIn('school_id', $filters->schoolIds);
            });
        }

        if ($filters->therapistIds) {
            $query->whereIn('assigned_therapist_id', $filters->therapistIds);
        }

        if ($filters->serviceIds) {
            $query->whereIn('primary_service_id', $filters->serviceIds);
        }

        if ($filters->statuses) {
            $statusValues = array_map(static fn (SSAStatus $status) => $status->value, $filters->statuses);
            $query->whereIn('status', $statusValues);
        }

        if ($filters->gradeLevel) {
            $query->whereHas('student.studentProfile', function (Builder $q) use ($filters) {
                $q->where('grade_level', $filters->gradeLevel); // @phpstan-ignore argument.type
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getCaseloadReport(CaseloadReportFilterDTO $filters): Collection
    {
        $query = ServiceSupportAgreement::query()
            ->where('status', $filters->status ?? SSAStatus::ACTIVE)
            ->with([
                'student',
                'student.studentProfile.school',
                'primaryService',
                'assignedTherapist',
            ]);

        if ($filters->schoolIds) {
            $query->whereHas('student.studentProfile', function (Builder $q) use ($filters) {
                $q->whereIn('school_id', $filters->schoolIds);
            });
        }

        if ($filters->therapistIds) {
            $query->whereIn('assigned_therapist_id', $filters->therapistIds);
        }

        if ($filters->serviceIds) {
            $query->whereIn('primary_service_id', $filters->serviceIds);
        }

        return $query->get();
    }

    public function getExpirationReport(ExpirationReportFilterDTO $filters): array
    {
        $today = Carbon::today();
        $expirationDate = $today->copy()->addDays($filters->expirationWindowDays);

        $baseQuery = ServiceSupportAgreement::query()
            ->with([
                'student',
                'student.studentProfile.school',
                'primaryService',
                'assignedTherapist',
            ]);

        if ($filters->schoolIds) {
            $baseQuery->whereHas('student.studentProfile', function (Builder $q) use ($filters) {
                $q->whereIn('school_id', $filters->schoolIds);
            });
        }

        if ($filters->therapistIds) {
            $baseQuery->whereIn('assigned_therapist_id', $filters->therapistIds);
        }

        if ($filters->serviceIds) {
            $baseQuery->whereIn('primary_service_id', $filters->serviceIds);
        }

        $upcoming = (clone $baseQuery)
            ->where('end_date', '>', $today->format('Y-m-d'))
            ->where('end_date', '<=', $expirationDate->format('Y-m-d'))
            ->get();

        $expired = (clone $baseQuery)
            ->where('end_date', '<', $today->format('Y-m-d'))
            ->where('status', SSAStatus::ACTIVE->value)
            ->get();

        $pending = (clone $baseQuery)
            ->where(function (Builder $q) {
                $q->where('status', SSAStatus::PENDING->value)
                    ->orWhere(function (Builder $subQ) {
                        $subQ->where('status', SSAStatus::ACTIVE->value)
                            ->whereNull('assigned_therapist_id');
                    });
            })
            ->get();

        // Get students with no active SSAs
        $studentsWithActiveSSAs = ServiceSupportAgreement::query()
            ->where('status', SSAStatus::ACTIVE->value)
            ->pluck('student_id')
            ->unique()
            ->toArray();

        $noCurrentQuery = \App\Models\User::query()
            ->whereHas('studentProfile', function (Builder $q) use ($filters) {
                if ($filters->schoolIds) {
                    $q->whereIn('school_id', $filters->schoolIds);
                }
            })
            ->whereNotIn('id', $studentsWithActiveSSAs ?: [0])
            ->with(['studentProfile.school']);

        $noCurrent = $noCurrentQuery->get()->map(function ($student) {
            return (object) [
                'id' => null,
                'student_id' => $student->id,
                'student' => $student,
                'primaryService' => null,
                'assignedTherapist' => null,
                'status' => null,
                'end_date' => null,
            ];
        });

        return [
            'upcoming' => $upcoming,
            'expired' => $expired,
            'pending' => $pending,
            'no_current' => $noCurrent,
        ];
    }

    public function findOriginalSsaForSubCoverage(int $studentId, int $serviceId, int $therapistId, string $sessionDate): ?ServiceSupportAgreement
    {
        return ServiceSupportAgreement::query()
            ->forStudent($studentId)
            ->forPrimaryService($serviceId)
            ->forAssignedTherapist($therapistId)
            ->effectiveOn($sessionDate)
            ->activeOrPending()
            ->orderByRaw("FIELD(status, 'active', 'pending')")
            ->orderByDesc('start_date')
            ->first();
    }
}
