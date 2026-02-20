<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Position;
use App\Models\User;

final class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Position $position): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Position $position): bool
    {
        return $this->isAdmin($user);
    }

    public function changeStatus(User $user, Position $position): bool
    {
        return $this->isAdmin($user);
    }

    public function export(User $user): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        return $role === Role::ADMIN;
    }
}
