<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\SchoolContract;
use App\Models\User;

final class SchoolContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, SchoolContract $contract): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, SchoolContract $contract): bool
    {
        return $this->isAdmin($user);
    }

    public function changeStatus(User $user, SchoolContract $contract): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }
}
