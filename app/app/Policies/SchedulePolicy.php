<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

final class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTherapist() || $user->isAdmin();
    }

    public function view(User $user, Schedule $schedule): bool
    {
        if ($user->isAdmin() || $this->ownsSchedule($user, $schedule)) {
            return true;
        }

        // Covering sub: when an accepted sub-request assigns this schedule to the user,
        // they need to view it to deliver the session and submit the log.
        return $this->isCoveringSub($user, $schedule);
    }

    public function create(User $user): bool
    {
        return $user->isTherapist();
    }

    public function update(User $user, Schedule $schedule): bool
    {
        // Covering subs can edit the schedule they're covering so they can adjust
        // session details before delivering it; only the owner may delete it.
        return $this->ownsSchedule($user, $schedule)
            || $this->isCoveringSub($user, $schedule);
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
        if ($user->isAdmin()) {
            return true;
        }

        return $this->ownsSchedule($user, $schedule);
    }

    private function ownsSchedule(User $user, Schedule $schedule): bool
    {
        return $user->isTherapist() && $schedule->therapist_id === $user->id;
    }

    private function isCoveringSub(User $user, Schedule $schedule): bool
    {
        return $user->isTherapist()
            && (int) ($schedule->sub_therapist_id ?? 0) === (int) $user->id;
    }
}
