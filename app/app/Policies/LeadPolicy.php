<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, ?Lead $lead = null): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ?Lead $lead = null): bool
    {
        return $this->isAdmin($user);
    }

    public function changeStatus(User $user, ?Lead $lead = null): bool
    {
        return $this->isAdmin($user);
    }

    public function convert(User $user, ?Lead $lead = null): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ?Lead $lead = null): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }
}
