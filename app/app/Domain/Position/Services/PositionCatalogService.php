<?php

declare(strict_types=1);

namespace App\Domain\Position\Services;

use App\Domain\Position\Repositories\PositionRepositoryInterface;
use App\DTOs\ChangePositionStatusDTO;
use App\DTOs\CreatePositionDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\PositionFilterDTO;
use App\DTOs\UpdatePositionDTO;
use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class PositionCatalogService
{
    public function __construct(
        private readonly PositionRepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<int, Position> */
    public function paginate(PositionFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:\Illuminate\Database\Eloquent\Collection<int,Position>}
     */
    public function listForDataTables(PositionFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /** @return Collection<int, Position> */
    public function all(PositionFilterDTO $filters): Collection
    {
        return $this->repository->all($filters);
    }

    public function create(CreatePositionDTO $dto): Position
    {
        return $this->repository->create($dto);
    }

    public function update(Position $position, UpdatePositionDTO $dto): Position
    {
        return $this->repository->update($position, $dto);
    }

    public function changeStatus(Position $position, ChangePositionStatusDTO $dto): Position
    {
        return $this->repository->changeStatus($position, $dto);
    }

    /** @return array{total: int, active: int, inactive: int} */
    public function metrics(): array
    {
        return $this->repository->metrics();
    }

    /** @return Collection<int, Position> */
    public function listActiveForSelect(): Collection
    {
        return $this->repository->listActiveForSelect();
    }
}
