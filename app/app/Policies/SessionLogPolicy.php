<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SessionLog;
use App\Models\User;

final class SessionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTherapist() || $user->isAdmin();
    }

    public function view(User $user, SessionLog $sessionLog): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTherapist()) {
            return $sessionLog->therapist_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isTherapist();
    }

    public function update(User $user, SessionLog $sessionLog): bool
    {
        if ($user->isAdmin()) {
            return $sessionLog->isDraft() || $sessionLog->isSubmitted();
        }

        if ($user->isTherapist()) {
            return $sessionLog->therapist_id === $user->id && $sessionLog->canEdit();
        }

        return false;
    }

    public function delete(User $user, SessionLog $sessionLog): bool
    {
        if ($user->isAdmin()) {
            return $sessionLog->isDraft() || $sessionLog->isSubmitted();
        }

        if ($user->isTherapist()) {
            return $sessionLog->therapist_id === $user->id && $sessionLog->canEdit();
        }

        return false;
    }

    public function submit(User $user, SessionLog $sessionLog): bool
    {
        if (! $user->isTherapist()) {
            return false;
        }

        if ($sessionLog->isApproved()) {
            return false;
        }

        // The sub therapist who performed the session can also submit.
        return (int) $sessionLog->therapist_id === (int) $user->id;
    }

    public function approve(User $user, SessionLog $sessionLog): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return $sessionLog->isSubmitted();
    }

    public function sendBack(User $user, SessionLog $sessionLog): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return $sessionLog->status?->canSendBack() ?? false;
    }

    public function cancel(User $user, SessionLog $sessionLog): bool
    {
        if ($user->isAdmin()) {
            return $sessionLog->status?->canCancel() ?? false;
        }

        if ($user->isTherapist()) {
            return $sessionLog->therapist_id === $user->id && ($sessionLog->status?->canCancel() ?? false);
        }

        return false;
    }
}
