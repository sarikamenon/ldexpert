<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TherapistBill;
use App\Models\User;

final class TherapistBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTherapist();
    }

    public function view(User $user, TherapistBill $bill): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTherapist()) {
            return $bill->therapist_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, TherapistBill $bill): bool
    {
        return $user->isAdmin() && $bill->isDraft();
    }

    public function send(User $user, TherapistBill $bill): bool
    {
        return $user->isAdmin() && $bill->isDraft();
    }

    public function delete(User $user, TherapistBill $bill): bool
    {
        return $user->isAdmin() && ($bill->isDraft() || $bill->isZeroAmount());
    }

    public function download(User $user, TherapistBill $bill): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTherapist()) {
            return $bill->therapist_id === $user->id;
        }

        return false;
    }
}
