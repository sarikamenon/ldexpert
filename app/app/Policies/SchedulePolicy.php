<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Schedule;
use App\Models\User;

final class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isTherapist($user) || $this->isAdmin($user);
    }

    public function view(User $user, Schedule $schedule): bool
    {
        if ($this->isAdmin($user) || $this->ownsSchedule($user, $schedule)) {
            return true;
        }

        // Covering sub: when an accepted sub-request assigns this schedule to the user,
        // they need to view it to deliver the session and submit the log.
        return $this->isTherapist($user)
            && (int) ($schedule->sub_therapist_id ?? 0) === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isTherapist($user);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->ownsSchedule($user, $schedule);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->ownsSchedule($user, $schedule);
    }

    public function createSubRequest(User $user, Schedule $schedule): bool
    {
        return $this->ownsSchedule($user, $schedule);
    }

    public function updateBillingStatus(User $user, Schedule $schedule): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->ownsSchedule($user, $schedule);
    }

    private function ownsSchedule(User $user, Schedule $schedule): bool
    {
        return $this->isTherapist($user) && $schedule->therapist_id === $user->id;
    }

    private function isTherapist(User $user): bool
    {
        return $user->role === Role::THERAPIST;
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }
}
