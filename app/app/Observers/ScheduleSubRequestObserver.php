<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;

final class ScheduleSubRequestObserver
{
    public function deleting(ScheduleSubRequest $request): void
    {
        if ($request->isForceDeleting()) {
            return;
        }

        $request->invitees()
            ->cursor()
            ->each(static fn (ScheduleSubRequestInvitee $invitee) => $invitee->delete());
    }

    public function restoring(ScheduleSubRequest $request): void
    {
        $request->invitees()
            ->onlyTrashed()
            ->where('deleted_at', $request->deleted_at)
            ->cursor()
            ->each(static fn (ScheduleSubRequestInvitee $invitee) => $invitee->restore());
    }
}
