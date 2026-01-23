<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\SchoolCalendarEvent;
use App\Models\User;

class SchoolCalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, SchoolCalendarEvent $event): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, SchoolCalendarEvent $event): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, SchoolCalendarEvent $event): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        return $role === Role::ADMIN;
    }
}
