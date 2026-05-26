<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\User;

final class ScheduleSubRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTherapist() || $user->isAdmin();
    }

    public function createSubRequest(User $user, Schedule $schedule): bool
    {
        return $user->isTherapist()
            && (int) $schedule->therapist_id === (int) $user->id;
    }

    public function view(User $user, ScheduleSubRequest $subRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTherapist() && (
            (int) $subRequest->requested_by_id === (int) $user->id
            || (int) $subRequest->accepted_by_id === (int) $user->id
            || $this->hasInvitedRow($user, $subRequest)
        );
    }

    public function accept(User $user, ScheduleSubRequest $subRequest): bool
    {
        return $user->isTherapist()
            && $subRequest->isOpen()
            && (int) $subRequest->requested_by_id !== (int) $user->id
            && $this->hasInvitedRow($user, $subRequest);
    }

    public function decline(User $user, ScheduleSubRequest $subRequest): bool
    {
        return $user->isTherapist()
            && $subRequest->isOpen()
            && (int) $subRequest->requested_by_id !== (int) $user->id
            && $this->hasInvitedRow($user, $subRequest);
    }

    public function manageInvitees(User $user, ScheduleSubRequest $subRequest): bool
    {
        if (! $subRequest->isOpen()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTherapist()
            && (int) $subRequest->requested_by_id === (int) $user->id;
    }

    public function cancel(User $user, ScheduleSubRequest $subRequest): bool
    {
        if (! $subRequest->isOpen()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTherapist()
            && (int) $subRequest->requested_by_id === (int) $user->id;
    }

    public function withdraw(User $user, ScheduleSubRequest $subRequest): bool
    {
        if (! $subRequest->isAccepted()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTherapist()
            && (int) $subRequest->requested_by_id === (int) $user->id;
    }

    private function hasInvitedRow(User $user, ScheduleSubRequest $subRequest): bool
    {
        return $subRequest->invitees()
            ->forTherapist($user)
            ->pending()
            ->exists();
    }
}
