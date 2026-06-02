<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\CannotDeleteScheduleWithMakeupException;
use App\Models\Schedule;

final class ScheduleObserver
{
    /**
     * @throws CannotDeleteScheduleWithMakeupException
     */
    public function deleting(Schedule $schedule): void
    {
        if ($schedule->isForceDeleting()) {
            return;
        }

        if ($schedule->makeupRequests()->blockingScheduleDeletion()->exists()) {
            throw new CannotDeleteScheduleWithMakeupException;
        }

        $schedule->subRequests()->get()->each->delete();
    }

    public function restoring(Schedule $schedule): void
    {
        $schedule->subRequests()
            ->onlyTrashed()
            ->where('deleted_at', $schedule->deleted_at)
            ->get()
            ->each
            ->restore();
    }
}
