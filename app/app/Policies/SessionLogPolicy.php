<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\SessionLog;
use App\Models\User;

final class SessionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::THERAPIST || $user->role === Role::ADMIN;
    }

    public function view(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role === Role::ADMIN) {
            return true;
        }

        if ($user->role === Role::THERAPIST) {
            return $sessionLog->therapist_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::THERAPIST;
    }

    public function update(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role === Role::ADMIN) {
            return $sessionLog->isDraft() || $sessionLog->isSubmitted();
        }

        if ($user->role === Role::THERAPIST) {
            return $sessionLog->therapist_id === $user->id && $sessionLog->canEdit();
        }

        return false;
    }

    public function delete(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role === Role::ADMIN) {
            return $sessionLog->isDraft() || $sessionLog->isSubmitted();
        }

        if ($user->role === Role::THERAPIST) {
            return $sessionLog->therapist_id === $user->id && $sessionLog->canEdit();
        }

        return false;
    }

    public function submit(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role !== Role::THERAPIST) {
            return false;
        }

        // Therapist can submit own log unless approved; service enforces draft/submitted transitions.
        return (int) $sessionLog->therapist_id === (int) $user->id && ! $sessionLog->isApproved();
    }

    public function approve(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role !== Role::ADMIN) {
            return false;
        }

        // Admins can only approve logs that are currently submitted.
        return $sessionLog->isSubmitted();
    }

    public function sendBack(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role !== Role::ADMIN) {
            return false;
        }

        return $sessionLog->status?->canSendBack() ?? false;
    }

    public function cancel(User $user, SessionLog $sessionLog): bool
    {
        if ($user->role === Role::ADMIN) {
            return $sessionLog->status->canCancel();
        }

        if ($user->role === Role::THERAPIST) {
            return $sessionLog->therapist_id === $user->id && $sessionLog->status->canCancel();
        }

        return false;
    }
}
