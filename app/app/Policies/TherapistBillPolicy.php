<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\TherapistBill;
use App\Models\User;

final class TherapistBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN || $user->role === Role::THERAPIST;
    }

    public function view(User $user, TherapistBill $bill): bool
    {
        if ($user->role === Role::ADMIN) {
            return true;
        }

        if ($user->role === Role::THERAPIST) {
            return $bill->therapist_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function send(User $user, TherapistBill $bill): bool
    {
        return $user->role === Role::ADMIN && $bill->isDraft();
    }

    public function download(User $user, TherapistBill $bill): bool
    {
        if ($user->role === Role::ADMIN) {
            return true;
        }

        if ($user->role === Role::THERAPIST) {
            return $bill->therapist_id === $user->id;
        }

        return false;
    }
}
