<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSAGoalRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\CreateSSAGoalDTO;
use App\DTOs\UpdateSSAGoalDTO;
use App\Enums\SSAGoalStatus;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SSAGoalService
{
    public function __construct(
        private readonly SSAGoalRepositoryInterface $goals,
        private readonly SSARepositoryInterface $ssas,
    ) {}

    /** @return Collection<int, SSAGoal> */
    public function listForSsa(int $ssaId): Collection
    {
        return $this->goals->listForSsa($ssaId)
            ->each(fn (SSAGoal $goal) => $this->attachCanTransitionFlag($goal));
    }

    /** @return Collection<int, SSAGoal> */
    public function listActiveForSsa(int $ssaId): Collection
    {
        return $this->goals->listActiveForSsa($ssaId)
            ->each(fn (SSAGoal $goal) => $this->attachCanTransitionFlag($goal));
    }

    /** @return Collection<int, SSAGoal> */
    public function listForStudent(int $studentId): Collection
    {
        return $this->goals->listForStudent($studentId)
            ->each(fn (SSAGoal $goal) => $this->attachCanTransitionFlag($goal));
    }

    private function attachCanTransitionFlag(SSAGoal $goal): void
    {
        $goal->can_transition_status = $goal->status === SSAGoalStatus::ACTIVE;
    }

    public function create(CreateSSAGoalDTO $dto): SSAGoal
    {
        $ssa = $this->ssas->find($dto->ssaId);

        if ($ssa === null) {
            throw new InvalidArgumentException('SSA not found.');
        }

        if ($ssa->student_id !== $dto->studentId) {
            throw new InvalidArgumentException('Student does not match the SSA.');
        }

        return DB::transaction(fn (): SSAGoal => $this->goals->create($dto));
    }

    public function update(SSAGoal $goal, UpdateSSAGoalDTO $dto): SSAGoal
    {
        return DB::transaction(fn (): SSAGoal => $this->goals->update($goal, $dto));
    }

    public function changeStatus(SSAGoal $goal, SSAGoalStatus $status): SSAGoal
    {
        return DB::transaction(fn (): SSAGoal => $this->goals->changeStatus($goal, $status));
    }

    /**
     * Get goal metrics for a specific SSA. Mastery rate is mastered ÷ total goals.
     *
     * @return array{total_goals: int, active_goals: int, mastered_goals: int, discontinued_goals: int, mastery_rate: float}
     */
    public function getMetricsForSsa(int $ssaId): array
    {
        return $this->goals->getMetricsForSsa($ssaId);
    }
}
