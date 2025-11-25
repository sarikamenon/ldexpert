<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\SSAAssignmentDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\UpdateSSADTO;
use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAAssignmentHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class EloquentSSARepository implements SSARepositoryInterface
{
    public function paginate(SSAFilterDTO $filters): LengthAwarePaginator
    {
        $query = $this->applyFilters(ServiceSupportAgreement::query(), $filters);

        return $query
            ->with([
                'student',
                'student.studentProfile.school',
                'primaryService',
                'additionalService',
                'assignedTherapist',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    public function find(int $id): ?ServiceSupportAgreement
    {
        return ServiceSupportAgreement::with([
            'student',
            'student.studentProfile.school',
            'primaryService',
            'additionalService',
            'assignedTherapist',
            'assignmentHistory.therapist',
            'assignmentHistory.assignedBy',
        ])->find($id);
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

            return $ssa->fresh();
        });
    }

    public function update(ServiceSupportAgreement $ssa, UpdateSSADTO $dto): ServiceSupportAgreement
    {
        $ssa->update($dto->toArray());

        return $ssa->fresh();
    }

    public function changeStatus(ServiceSupportAgreement $ssa, ChangeSSAStatusDTO $dto): ServiceSupportAgreement
    {
        $ssa->update([
            'status' => $dto->status->value,
        ]);

        return $ssa->fresh();
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

            return $ssa->fresh();
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

            return $ssa->fresh();
        });
    }

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

    public function checkOverlappingSSAs(int $studentId, int $serviceId, string $startDate, string $endDate, ?int $excludeSsaId = null): Collection
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

    private function applyFilters(Builder $query, SSAFilterDTO $filters): Builder
    {
        if ($filters->search) {
            $query->where(function (Builder $q) use ($filters) {
                $q->whereHas('student', function (Builder $studentQuery) use ($filters) {
                    $studentQuery->where('name', 'like', '%' . $filters->search . '%');
                })
                    ->orWhereHas('primaryService', function (Builder $serviceQuery) use ($filters) {
                        $serviceQuery->where('name', 'like', '%' . $filters->search . '%');
                    })
                    ->orWhereHas('assignedTherapist', function (Builder $therapistQuery) use ($filters) {
                        $therapistQuery->where('name', 'like', '%' . $filters->search . '%');
                    });
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->studentId) {
            $query->where('student_id', $filters->studentId);
        }

        if ($filters->serviceId) {
            $query->where('primary_service_id', $filters->serviceId);
        }

        if ($filters->therapistId) {
            $query->where('assigned_therapist_id', $filters->therapistId);
        }

        return $query;
    }
}
