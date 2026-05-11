<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\SSA\Repositories\SSAGoalRepositoryInterface;
use App\DTOs\CreateSSAGoalDTO;
use App\DTOs\UpdateSSAGoalDTO;
use App\Enums\SSAGoalStatus;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Collection;

final class EloquentSSAGoalRepository implements SSAGoalRepositoryInterface
{
    public function find(int $id): ?SSAGoal
    {
        return SSAGoal::query()->find($id);
    }

    /** @return Collection<int, SSAGoal> */
    public function listForSsa(int $ssaId): Collection
    {
        return SSAGoal::query()
            ->forSsa($ssaId)
            ->orderForList()
            ->get();
    }

    /** @return Collection<int, SSAGoal> */
    public function listActiveForSsa(int $ssaId): Collection
    {
        return SSAGoal::query()
            ->forSsa($ssaId)
            ->activeStatus()
            ->orderForList()
            ->get();
    }

    public function create(CreateSSAGoalDTO $dto): SSAGoal
    {
        return SSAGoal::create($dto->toArray());
    }

    public function update(SSAGoal $goal, UpdateSSAGoalDTO $dto): SSAGoal
    {
        $goal->update($dto->toArray());

        return $goal->refresh();
    }

    public function changeStatus(SSAGoal $goal, SSAGoalStatus $status): SSAGoal
    {
        $goal->update(['status' => $status->value]);

        return $goal->refresh();
    }

    public function existsActiveForSsa(int $ssaId): bool
    {
        return SSAGoal::query()
            ->forSsa($ssaId)
            ->activeStatus()
            ->exists();
    }
}
