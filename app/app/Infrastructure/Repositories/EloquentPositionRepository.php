<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Position\Repositories\PositionRepositoryInterface;
use App\DTOs\ChangePositionStatusDTO;
use App\DTOs\CreatePositionDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\PositionFilterDTO;
use App\DTOs\UpdatePositionDTO;
use App\Enums\PositionStatus;
use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentPositionRepository implements PositionRepositoryInterface
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /** @return LengthAwarePaginator<int, Position> */
    public function paginate(PositionFilterDTO $filters): LengthAwarePaginator
    {
        return $this->applyFilters(Position::query()->with('services'), $filters)
            ->orderBy('name')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /** @return Collection<int, Position> */
    public function all(PositionFilterDTO $filters): Collection
    {
        return $this->applyFilters(Position::query()->with('services'), $filters)
            ->orderBy('name')
            ->get();
    }

    public function listForDataTables(PositionFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters(Position::query()->with('services'), $filters);

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('positions.id');

        if ($params->searchValue) {
            $baseQuery->where('name', 'like', '%'.$params->searchValue.'%');
        }

        $recordsFiltered = (clone $baseQuery)->count('positions.id');

        $orderColumn = $params->orderColumn ?? 'positions.name';
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

    public function create(CreatePositionDTO $dto): Position
    {
        $position = Position::create($dto->toArray());

        if (! empty($dto->serviceIds)) {
            $position->services()->attach($dto->serviceIds);
            $this->recordServicesSyncedAudit($position, oldIds: []);
        }

        return $position->load('services');
    }

    public function update(Position $position, UpdatePositionDTO $dto): Position
    {
        $position->update($dto->toArray());

        if (! empty($dto->serviceIds)) {
            $oldIds = $position->serviceIdsSnapshot();
            $position->services()->sync($dto->serviceIds);
            $this->recordServicesSyncedAudit($position, oldIds: $oldIds);
        }

        /** @var Position $freshPosition */
        $freshPosition = $position->fresh('services');

        return $freshPosition;
    }

    /**
     * Emit a `services_synced` audit on the parent Position when the
     * pivot set actually changed. Pivot writes bypass model events,
     * so we capture before/after and delegate to AuditRecorder.
     *
     * @param  array<int, int>  $oldIds
     */
    private function recordServicesSyncedAudit(Position $position, array $oldIds): void
    {
        $newIds = $position->refresh()->serviceIdsSnapshot();

        if ($oldIds === $newIds) {
            return;
        }

        $this->auditRecorder->record(
            auditable: $position,
            event: 'services_synced',
            oldValues: ['service_ids' => $oldIds],
            newValues: ['service_ids' => $newIds],
        );
    }

    public function changeStatus(Position $position, ChangePositionStatusDTO $dto): Position
    {
        $position->update($dto->toArray());

        /** @var Position $freshPosition */
        $freshPosition = $position->fresh();

        return $freshPosition;
    }

    public function metrics(): array
    {
        $total = Position::count();
        $active = Position::where('status', PositionStatus::ACTIVE)->count();
        $inactive = Position::where('status', PositionStatus::INACTIVE)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    /** @return Collection<int, Position> */
    public function listActiveForSelect(): Collection
    {
        return Position::query()
            ->where('status', PositionStatus::ACTIVE)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Builder<Position>  $query
     * @return Builder<Position>
     */
    private function applyFilters(Builder $query, PositionFilterDTO $filters): Builder
    {
        if ($filters->search) {
            $query->where('name', 'like', '%'.$filters->search.'%');
        }

        if ($filters->status) {
            $query->where('status', $filters->status->value);
        }

        return $query;
    }
}
