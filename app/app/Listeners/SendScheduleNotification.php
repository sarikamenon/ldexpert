<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
use App\Mail\ScheduleNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduleNotification implements ShouldQueue
{
    public function handle(ScheduleCreated|ScheduleUpdated $event): void
    {
        $schedule = $event->schedule;

        // Eager load relationships if missing to avoid N+1 in loop/mail view
        $schedule->loadMissing(['therapist', 'student.studentProfile', 'service']);

        $type = $event instanceof ScheduleCreated ? 'created' : 'updated';

        try {
            // Notify Therapist
            if ($schedule->therapist && $schedule->therapist->email) {
                Mail::to($schedule->therapist->email)->send(
                    new ScheduleNotificationMail($schedule, $type, isRecipientStudent: false)
                );
            }

            // Student side: only schedule_email (no student user email or parent/guardian emails)
            if ($schedule->student?->studentProfile?->schedule_email) {
                Mail::to($schedule->student->studentProfile->schedule_email)->send(
                    new ScheduleNotificationMail($schedule, $type, isRecipientStudent: true)
                );
            }
        } catch (\Throwable $e) {
            Log::error('SendScheduleNotification: failed to send mail', [
                'schedule_id' => $schedule->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
