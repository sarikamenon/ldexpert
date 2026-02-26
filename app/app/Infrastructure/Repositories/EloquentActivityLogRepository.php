<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\ActivityLog\Repositories\ActivityLogRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ActivityLog
    {
        return ActivityLog::create($attributes);
    }

    /** @return Collection<int, ActivityLog> */
    public function recent(int $limit = 5): Collection
    {
        return ActivityLog::with('user')
            ->latest('created_at')
            ->take($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, ActivityLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->baseQuery();
        $this->applyFilters($baseQuery, $filters);

        $recordsTotal = (clone $baseQuery)->count('activity_logs.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('activity_logs.description', 'like', '%'.$search.'%')
                    ->orWhere('activity_logs.action', 'like', '%'.$search.'%')
                    ->orWhere('activity_logs.model_type', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', '%'.$search.'%'); // @phpstan-ignore argument.type
                    });
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('activity_logs.id');

        $orderColumn = $params->orderColumn ?? 'activity_logs.created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';

        if (str_starts_with($orderColumn, 'users.')) {
            $baseQuery->join('users', 'activity_logs.user_id', '=', 'users.id')
                ->select('activity_logs.*')
                ->orderBy($orderColumn, $orderDir);
        } else {
            $baseQuery->orderBy($orderColumn, $orderDir);
        }

        /** @var Collection<int, ActivityLog> $rows */
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

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ActivityLog>
     */
    public function all(array $filters): Collection
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /** @return Collection<int, string> */
    public function distinctActions(): Collection
    {
        return ActivityLog::distinct()
            ->pluck('action')
            ->sort()
            ->values();
    }

    /** @return Collection<int, string> */
    public function distinctModelTypes(): Collection
    {
        /** @var Collection<int, string> */
        return ActivityLog::distinct()
            ->pluck('model_type')
            ->map(static fn (?string $type) => $type ? class_basename($type) : null)
            ->filter()
            ->sort()
            ->values();
    }

    /** @return Builder<ActivityLog> */
    private function baseQuery(): Builder
    {
        return ActivityLog::with('user')->latest('created_at');
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $query->where('description', 'like', '%'.$filters['search'].'%');
        }
    }
}
