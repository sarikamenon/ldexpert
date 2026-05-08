<?php

declare(strict_types=1);

namespace App\Domain\SSA\Repositories;

use App\DTOs\CreateSSAGoalDTO;
use App\DTOs\UpdateSSAGoalDTO;
use App\Enums\SSAGoalStatus;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Collection;

interface SSAGoalRepositoryInterface
{
    public function find(int $id): ?SSAGoal;

    /** @return Collection<int, SSAGoal> */
    public function listForSsa(int $ssaId): Collection;

    /** @return Collection<int, SSAGoal> */
    public function listActiveForSsa(int $ssaId): Collection;

    public function create(CreateSSAGoalDTO $dto): SSAGoal;

    public function update(SSAGoal $goal, UpdateSSAGoalDTO $dto): SSAGoal;

    public function changeStatus(SSAGoal $goal, SSAGoalStatus $status): SSAGoal;

    public function existsActiveForSsa(int $ssaId): bool;
}
