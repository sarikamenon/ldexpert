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

    /** @return Collection<int, SSAGoal> */
    public function listForStudent(int $studentId): Collection
    {
        return SSAGoal::query()
            ->forStudent($studentId)
            ->with('ssa.primaryService')
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

    /** @return array{total_goals: int, active_goals: int, mastered_goals: int, discontinued_goals: int, mastery_rate: float} */
    public function getMetricsForSsa(int $ssaId): array
    {
        $goals = SSAGoal::query()
            ->forSsa($ssaId)
            ->get();

        $total = $goals->count();
        $active = $goals->where('status', SSAGoalStatus::ACTIVE)->count();
        $mastered = $goals->where('status', SSAGoalStatus::MASTERED)->count();
        $discontinued = $goals->where('status', SSAGoalStatus::DISCONTINUED)->count();

        $completedGoals = $mastered + $discontinued;
        $masteryRate = $completedGoals > 0
            ? ($mastered / $completedGoals) * 100
            : 0.0;

        return [
            'total_goals' => $total,
            'active_goals' => $active,
            'mastered_goals' => $mastered,
            'discontinued_goals' => $discontinued,
            'mastery_rate' => round($masteryRate, 1),
        ];
    }
}
