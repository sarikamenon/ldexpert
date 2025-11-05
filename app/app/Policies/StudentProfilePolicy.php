<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\StudentProfile;
use App\Models\User;

class StudentProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array(($user->role instanceof Role ? $user->role->value : $user->role), ['admin', 'therapist'], true);
    }

    public function create(User $user): bool
    {
        return ($user->role instanceof Role ? $user->role : Role::tryFrom($user->role)) === Role::THERAPIST
            || ($user->role instanceof Role ? $user->role : Role::tryFrom($user->role)) === Role::ADMIN;
    }

    public function view(User $user, StudentProfile $student): bool
    {
        $userRole = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        if ($userRole === Role::ADMIN) {
            return true;
        }

        if ($userRole === Role::THERAPIST) {
            // therapist can view if assigned
            return $user->students()->where('users.id', $student->user_id)->exists();
        }

        if ($userRole === Role::PARENT) {
            return $student->parent_id === $user->id;
        }

        return $userRole === Role::STUDENT && $student->user_id === $user->id;
    }

    public function update(User $user, StudentProfile $student): bool
    {
        $userRole = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        if ($userRole === Role::ADMIN) {
            return true;
        }

        if ($userRole === Role::THERAPIST) {
            return $user->students()->where('users.id', $student->user_id)->exists();
        }

        return false;
    }
}
