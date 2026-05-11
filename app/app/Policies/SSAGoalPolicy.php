<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use App\Models\User;

final class SSAGoalPolicy
{
    public function viewAny(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $user->isAdmin() || $this->isAssignedTherapist($user, $ssa);
    }

    public function view(User $user, SSAGoal $goal): bool
    {
        return $this->canActOnGoal($user, $goal);
    }

    public function create(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $user->isAdmin() || $this->isAssignedTherapist($user, $ssa);
    }

    public function update(User $user, SSAGoal $goal): bool
    {
        return $this->canActOnGoal($user, $goal);
    }

    public function changeStatus(User $user, SSAGoal $goal): bool
    {
        return $this->canActOnGoal($user, $goal);
    }

    private function canActOnGoal(User $user, SSAGoal $goal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ssa = $goal->ssa;

        return $ssa !== null && $this->isAssignedTherapist($user, $ssa);
    }

    private function isAssignedTherapist(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $user->isTherapist() && $ssa->assigned_therapist_id === $user->id;
    }
}
