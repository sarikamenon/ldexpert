<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\QGlobRequestStatus;
use App\Enums\Role;
use App\Models\QGlobRequest;
use App\Models\User;

final class QGlobRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isTherapist($user) || $this->isAdmin($user);
    }

    public function view(User $user, QGlobRequest $request): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isTherapist($user) && (int) $request->requested_by_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isTherapist($user);
    }

    public function delete(User $user, QGlobRequest $request): bool
    {
        if (! $this->isTherapist($user)) {
            return false;
        }

        return (int) $request->requested_by_id === $user->id
            && $request->status === QGlobRequestStatus::PENDING;
    }

    public function respond(User $user, QGlobRequest $request): bool
    {
        if (! $this->isAdmin($user)) {
            return false;
        }

        return $request->status === QGlobRequestStatus::PENDING;
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    private function isTherapist(User $user): bool
    {
        return $user->role === Role::THERAPIST;
    }
}
