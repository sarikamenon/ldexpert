<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\BillingSchedule;
use App\Models\User;

final class BillingSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function view(User $user, BillingSchedule $schedule): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function update(User $user, BillingSchedule $schedule): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function delete(User $user, BillingSchedule $schedule): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function runNow(User $user, BillingSchedule $schedule): bool
    {
        return $user->role === Role::ADMIN && $schedule->is_active;
    }
}
