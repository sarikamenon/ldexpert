<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScheduleMakeupRequest;
use App\Models\User;

final class ScheduleMakeupRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTherapist() || $user->isAdmin();
    }

    public function view(User $user, ScheduleMakeupRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTherapist() && (int) $request->therapist_id === (int) $user->id;
    }

    public function decline(User $user, ScheduleMakeupRequest $request): bool
    {
        return $user->isTherapist()
            && (int) $request->therapist_id === (int) $user->id
            && ($request->isPending() || $request->isSent())
            && ! $request->isResponded();
    }

    public function book(User $user, ScheduleMakeupRequest $request): bool
    {
        return $user->isTherapist()
            && (int) $request->therapist_id === (int) $user->id
            && $request->isRequested()
            && $request->makeup_schedule_id === null;
    }
}
