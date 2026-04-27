<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ScheduleEmailType;
use App\Events\ScheduleCreated;
use App\Events\ScheduleEmailSent;
use App\Events\ScheduleUpdated;
use App\Mail\ScheduleNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class SendScheduleNotification implements ShouldQueue
{
    public function handle(ScheduleCreated|ScheduleUpdated $event): void
    {
        $schedule = $event->schedule;

        // Eager load relationships if missing to avoid N+1 in loop/mail view
        $schedule->loadMissing(['therapist', 'student.studentProfile', 'service']);

        $isCreated = $event instanceof ScheduleCreated;
        $mailType = $isCreated ? 'created' : 'updated';
        $logType = $isCreated
            ? ScheduleEmailType::NOTIFICATION_CREATED
            : ScheduleEmailType::NOTIFICATION_UPDATED;

        // Notify Therapist
        if ($schedule->therapist && $schedule->therapist->email) {
            Mail::to($schedule->therapist->email)->send(
                new ScheduleNotificationMail($schedule, $mailType, isRecipientStudent: false)
            );
            Event::dispatch(new ScheduleEmailSent($schedule->id, $logType, $schedule->therapist->email));
        }

        // Student side: only schedule_email (no student user email or parent/guardian emails)
        if ($schedule->student?->studentProfile?->schedule_email) {
            $studentEmail = $schedule->student->studentProfile->schedule_email;
            Mail::to($studentEmail)->send(
                new ScheduleNotificationMail($schedule, $mailType, isRecipientStudent: true)
            );
            Event::dispatch(new ScheduleEmailSent($schedule->id, $logType, $studentEmail));
        }
    }
}
