<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
use App\Mail\ScheduleNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendScheduleNotification implements ShouldQueue
{
    public function handle(ScheduleCreated|ScheduleUpdated $event): void
    {
        $schedule = $event->schedule;

        // Eager load relationships if missing to avoid N+1 in loop/mail view
        $schedule->loadMissing(['therapist', 'student', 'service']);

        $type = $event instanceof ScheduleCreated ? 'created' : 'updated';

        // Notify Therapist
        if ($schedule->therapist && $schedule->therapist->email) {
            Mail::to($schedule->therapist->email)->send(
                new ScheduleNotificationMail($schedule, $type, isRecipientStudent: false)
            );
        }

        // Notify Student
        if ($schedule->student && $schedule->student->email) {
            Mail::to($schedule->student->email)->send(
                new ScheduleNotificationMail($schedule, $type, isRecipientStudent: true)
            );
        }
    }
}
