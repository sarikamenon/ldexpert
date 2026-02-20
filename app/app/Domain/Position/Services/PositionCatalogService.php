<?php

declare(strict_types=1);

namespace App\Domain\Position\Services;

use App\Domain\Position\Repositories\PositionRepositoryInterface;
use App\DTOs\ChangePositionStatusDTO;
use App\DTOs\CreatePositionDTO;
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

    public function paginate(PositionFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

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

    public function metrics(): array
    {
        return $this->repository->metrics();
    }

    public function listActiveForSelect(): Collection
    {
        return $this->repository->listActiveForSelect();
    }
}
