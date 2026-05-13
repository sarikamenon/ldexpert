<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSAGoalRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\CreateSSAGoalDTO;
use App\DTOs\UpdateSSAGoalDTO;
use App\Enums\SSAGoalStatus;
use App\Http\Support\SSAGoalReturnTo;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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

    /**
     * Payload for the student profile "Goals" tab (admin: all SSAs; therapist: assigned SSAs only).
     *
     * @return array{
     *     goals: Collection<int, SSAGoal>,
     *     studentSsasForGoalsTab: SupportCollection<int, ServiceSupportAgreement>,
     *     activeCount: int,
     *     masteredCount: int,
     *     discontinuedCount: int,
     *     goalCreateReturnTo: string
     * }
     */
    public function goalsTabViewDataForStudent(int $studentId, ?int $scopeTherapistId = null): array
    {
        $goals = $this->listForStudent($studentId);

        $ssaRows = $scopeTherapistId === null
            ? $this->ssas->getSSAsForStudentMetrics($studentId)
            : $this->ssas->getSSAsForMetrics($studentId, $scopeTherapistId);

        /** @var SupportCollection<int, ServiceSupportAgreement> $studentSsasForGoalsTab */
        $studentSsasForGoalsTab = $ssaRows->sortByDesc('id')->values();

        return [
            'goals' => $goals,
            'studentSsasForGoalsTab' => $studentSsasForGoalsTab,
            'activeCount' => $goals->filter(static fn (SSAGoal $g): bool => $g->status->isActive())->count(),
            'masteredCount' => $goals->filter(static fn (SSAGoal $g): bool => $g->status->isMastered())->count(),
            'discontinuedCount' => $goals->filter(static fn (SSAGoal $g): bool => $g->status->isDiscontinued())->count(),
            'goalCreateReturnTo' => SSAGoalReturnTo::StudentGoalsTab->value,
        ];
    }
}
