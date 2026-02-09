<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\ActivityLog\Repositories\ActivityLogRepositoryInterface;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog
    {
        return ActivityLog::create($attributes);
    }

    public function recent(int $limit = 5): Collection
    {
        return ActivityLog::with('user')
            ->latest('created_at')
            ->take($limit)
            ->get();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function all(array $filters): Collection
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    public function distinctActions(): Collection
    {
        return ActivityLog::distinct()
            ->pluck('action')
            ->sort()
            ->values();
    }

    public function distinctModelTypes(): Collection
    {
        return ActivityLog::distinct()
            ->pluck('model_type')
            ->map(static fn (?string $type) => $type ? class_basename($type) : null)
            ->filter()
            ->sort()
            ->values();
    }

    private function baseQuery(): Builder
    {
        return ActivityLog::with('user')->latest('created_at');
    }

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
