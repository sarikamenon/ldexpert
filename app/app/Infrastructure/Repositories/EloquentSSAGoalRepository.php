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

    /**
     * Mastery rate is mastered goals divided by total goals (same formula as student-level goal metrics).
     *
     * @return array{total_goals: int, active_goals: int, mastered_goals: int, discontinued_goals: int, mastery_rate: float}
     */
    public function getMetricsForSsa(int $ssaId): array
    {
        $query = SSAGoal::query()->forSsa($ssaId);
        $statusColumn = $query->qualifyColumn('status');

        /** @var object{total_goals: string|float|int|null, active_goals: string|float|int|null, mastered_goals: string|float|int|null, discontinued_goals: string|float|int|null}|null $row */
        $row = $query
            ->selectRaw('COUNT(*) as total_goals')
            ->selectRaw("SUM(CASE WHEN {$statusColumn} = ? THEN 1 ELSE 0 END) as active_goals", [SSAGoalStatus::ACTIVE->value])
            ->selectRaw("SUM(CASE WHEN {$statusColumn} = ? THEN 1 ELSE 0 END) as mastered_goals", [SSAGoalStatus::MASTERED->value])
            ->selectRaw("SUM(CASE WHEN {$statusColumn} = ? THEN 1 ELSE 0 END) as discontinued_goals", [SSAGoalStatus::DISCONTINUED->value])
            ->toBase()
            ->first();

        if ($row === null) {
            return [
                'total_goals' => 0,
                'active_goals' => 0,
                'mastered_goals' => 0,
                'discontinued_goals' => 0,
                'mastery_rate' => 0.0,
            ];
        }

        $total = (int) ($row->total_goals ?? 0);
        $active = (int) ($row->active_goals ?? 0);
        $mastered = (int) ($row->mastered_goals ?? 0);
        $discontinued = (int) ($row->discontinued_goals ?? 0);

        $masteryRate = $total > 0
            ? round(($mastered / $total) * 100, 1)
            : 0.0;

        return [
            'total_goals' => $total,
            'active_goals' => $active,
            'mastered_goals' => $mastered,
            'discontinued_goals' => $discontinued,
            'mastery_rate' => $masteryRate,
        ];
    }
}
