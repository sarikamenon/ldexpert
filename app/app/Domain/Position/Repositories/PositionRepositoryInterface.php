<?php

declare(strict_types=1);

namespace App\Domain\Position\Repositories;

use App\DTOs\ChangePositionStatusDTO;
use App\DTOs\CreatePositionDTO;
use App\DTOs\PositionFilterDTO;
use App\DTOs\UpdatePositionDTO;
use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PositionRepositoryInterface
{
    public function paginate(PositionFilterDTO $filters): LengthAwarePaginator;

    public function all(PositionFilterDTO $filters): Collection;

    public function create(CreatePositionDTO $dto): Position;

    public function update(Position $position, UpdatePositionDTO $dto): Position;

    public function changeStatus(Position $position, ChangePositionStatusDTO $dto): Position;

    /**
     * @return array{total:int,active:int,inactive:int}
     */
    public function metrics(): array;

    public function listActiveForSelect(): Collection;
}
