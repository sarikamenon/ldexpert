<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScheduleMakeupAvailability;
use App\Models\User;

final class ScheduleMakeupAvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTherapist() || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isTherapist();
    }

    public function delete(User $user, ScheduleMakeupAvailability $availability): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTherapist() && (int) $availability->therapist_id === (int) $user->id;
    }
}
