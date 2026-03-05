<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ServiceAlias;
use App\Models\User;

final class ServiceAliasPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, ServiceAlias $serviceAlias): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ServiceAlias $serviceAlias): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ServiceAlias $serviceAlias): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }
}
