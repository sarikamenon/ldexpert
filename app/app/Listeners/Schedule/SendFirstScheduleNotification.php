<?php

declare(strict_types=1);

namespace App\Listeners\Schedule;

use App\Events\Schedule\Created;
use App\Mail\Schedule\FirstScheduleManagerMail;
use App\Models\School;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * On the first schedule created for any student of a private-student school,
 * email the school manager once to prompt the first (family) invoice (§9).
 *
 * Fires once per school — the persistent guard lives on
 * schools.first_schedule_notified_at, set atomically so concurrent creates
 * cannot send twice.
 */
class SendFirstScheduleNotification implements ShouldQueue
{
    public function handle(Created $event): void
    {
        $schedule = $event->schedule;

        if ($schedule->school_id === null) {
            return;
        }

        $school = $schedule->school;
        if ($school === null || $school->is_private_student !== true) {
            return;
        }

        // Already notified — nothing to do.
        if ($school->first_schedule_notified_at !== null) {
            return;
        }

        // Atomic claim: only the update that flips null → now() proceeds, so a
        // race between concurrent first schedules sends exactly one email.
        $claimed = School::query()
            ->whereKey($school->id)
            ->whereNull('first_schedule_notified_at')
            ->update(['first_schedule_notified_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $manager = $school->manager;
        if ($manager === null || ! $manager->email) {
            Log::warning('First-schedule reminder skipped: school has no manager/email', [
                'school_id' => $school->id,
            ]);

            return;
        }

        // Side-effect: must never break schedule creation (CLAUDE.md).
        try {
            Mail::to($manager->email)->send(
                new FirstScheduleManagerMail($school, (string) $manager->name)
            );
        } catch (\Throwable $e) {
            Log::error('First-schedule reminder email failed to send', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
