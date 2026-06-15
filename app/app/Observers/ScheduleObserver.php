<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Schedule;

final class ScheduleObserver
{
    public function deleting(Schedule $schedule): void
    {
        if ($schedule->isForceDeleting()) {
            return;
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
