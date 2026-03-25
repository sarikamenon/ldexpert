<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\QGlobRequest\Repositories\QGlobRequestRepositoryInterface;
use App\DTOs\CreateQGlobRequestDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\QGlobRequestFilterDTO;
use App\DTOs\RespondQGlobRequestDTO;
use App\Enums\QGlobRequestStatus;
use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Models\QGlobRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class EloquentQGlobRequestRepository implements QGlobRequestRepositoryInterface
{
    public function create(CreateQGlobRequestDTO $dto): QGlobRequest
    {
        /** @var QGlobRequest $created */
        $created = QGlobRequest::query()->create(array_merge($dto->toArray(), [
            'status' => QGlobRequestStatus::PENDING,
        ]));

        return $created->fresh(['requestedBy', 'student']);
    }

    public function find(int $id): ?QGlobRequest
    {
        return QGlobRequest::query()
            ->with(['requestedBy', 'student.studentProfile.school', 'respondedBy'])
            ->find($id);
    }

    public function respond(QGlobRequest $request, RespondQGlobRequestDTO $dto): QGlobRequest
    {
        $request->update([
            'status' => $dto->status,
            'admin_response' => $dto->adminResponse,
            'responded_by_id' => $dto->respondedById,
            'responded_at' => Carbon::now(),
        ]);

        /** @var QGlobRequest $fresh */
        $fresh = $request->fresh(['requestedBy', 'student.studentProfile.school', 'respondedBy']);

        return $fresh;
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, QGlobRequest>}
     */
    public function listForDataTables(
        QGlobRequestFilterDTO $filters,
        DataTablesParamsDTO $params,
        ?int $therapistId,
    ): array {
        $baseQuery = QGlobRequest::query()->select('qglob_requests.*');

        if ($therapistId !== null) {
            $baseQuery->where('qglob_requests.requested_by_id', $therapistId);
        }

        $this->applyFilters($baseQuery, $filters);

        $recordsTotal = (clone $baseQuery)->count('qglob_requests.id');

        if ($params->searchValue !== null && $params->searchValue !== '') {
            $term = '%'.$params->searchValue.'%';
            $baseQuery->where(function (Builder $q) use ($term): void {
                $q->where('qglob_requests.note', 'like', $term)
                    ->orWhere('qglob_requests.admin_response', 'like', $term)
                    ->orWhereHas('requestedBy', function (Builder $userQuery) use ($term): void {
                        $userQuery->where('name', 'like', $term);
                    })
                    ->orWhereHas('student', function (Builder $userQuery) use ($term): void {
                        $userQuery->where('name', 'like', $term);
                    });
            });
        }

        $recordsFiltered = (clone $baseQuery)->count('qglob_requests.id');

        $orderColumn = $params->orderColumn ?? 'qglob_requests.requested_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir)
            ->orderBy('qglob_requests.id', $orderDir);

        /** @var EloquentCollection<int, QGlobRequest> $rows */
        $rows = (clone $baseQuery)
            ->with(['requestedBy', 'student.studentProfile.school'])
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    private function applyFilters(Builder $query, QGlobRequestFilterDTO $filters): void
    {
        if ($filters->therapistId !== null) {
            $query->where('qglob_requests.requested_by_id', $filters->therapistId);
        }

        if ($filters->status !== null) {
            $query->where('qglob_requests.status', $filters->status);
        }

        if ($filters->dateFrom !== null) {
            $query->whereDate('qglob_requests.requested_date', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate('qglob_requests.requested_date', '<=', $filters->dateTo);
        }
    }

    /** @return EloquentCollection<int, User> */
    public function listEligibleStudentsForTherapist(int $therapistId): EloquentCollection
    {
        return User::query()
            ->where('role', Role::STUDENT)
            ->whereHas('studentProfile.ssas', function (Builder $q) use ($therapistId): void {
                $q->where('assigned_therapist_id', $therapistId)
                    ->where('status', SSAStatus::ACTIVE);
            })
            ->orderBy('name')
            ->get();
    }

    public function isStudentEligibleForTherapist(int $studentId, int $therapistId): bool
    {
        return User::query()
            ->whereKey($studentId)
            ->where('role', Role::STUDENT)
            ->whereHas('studentProfile.ssas', function (Builder $q) use ($therapistId): void {
                $q->where('assigned_therapist_id', $therapistId)
                    ->where('status', SSAStatus::ACTIVE);
            })
            ->exists();
    }

    public function delete(QGlobRequest $request): bool
    {
        return (bool) $request->delete();
    }
}
