<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Position\Repositories\PositionRepositoryInterface;
use App\DTOs\ChangePositionStatusDTO;
use App\DTOs\CreatePositionDTO;
use App\DTOs\PositionFilterDTO;
use App\DTOs\UpdatePositionDTO;
use App\Enums\PositionStatus;
use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentPositionRepository implements PositionRepositoryInterface
{
    public function paginate(PositionFilterDTO $filters): LengthAwarePaginator
    {
        return $this->applyFilters(Position::query()->with('services'), $filters)
            ->orderBy('name')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    public function all(PositionFilterDTO $filters): Collection
    {
        return $this->applyFilters(Position::query()->with('services'), $filters)
            ->orderBy('name')
            ->get();
    }

    public function create(CreatePositionDTO $dto): Position
    {
        $position = Position::create($dto->toArray());

        if (! empty($dto->serviceIds)) {
            $position->services()->attach($dto->serviceIds);
        }

        return $position->load('services');
    }

    public function update(Position $position, UpdatePositionDTO $dto): Position
    {
        $position->update($dto->toArray());

        return $position->fresh('services');
    }

    public function changeStatus(Position $position, ChangePositionStatusDTO $dto): Position
    {
        $position->update($dto->toArray());

        return $position->fresh();
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

    public function listActiveForSelect(): Collection
    {
        return Position::query()
            ->where('status', PositionStatus::ACTIVE)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

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
