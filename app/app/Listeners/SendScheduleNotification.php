<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ScheduleEmailType;
use App\Events\ScheduleCreated;
use App\Events\ScheduleEmailSent;
use App\Events\ScheduleUpdated;
use App\Mail\ScheduleNotificationMail;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduleNotification implements ShouldQueue
{
    public function handle(ScheduleCreated|ScheduleUpdated $event): void
    {
        $schedule = $event->schedule;

        // Skip notifications for past-date schedules created retroactively
        if ($schedule->schedule_date->startOfDay()->lt(Carbon::today())) {
            return;
        }

        // Eager load relationships if missing to avoid N+1 in loop/mail view
        $schedule->loadMissing(['therapist', 'student.studentProfile', 'service']);

        $isCreated = $event instanceof ScheduleCreated;
        $mailType = $isCreated ? 'created' : 'updated';
        $logType = $isCreated
            ? ScheduleEmailType::NOTIFICATION_CREATED
            : ScheduleEmailType::NOTIFICATION_UPDATED;

        if ($schedule->service && ! $schedule->service->allowsScheduleEmail()) {
            return;
        }

        // Notify Therapist
        if ($schedule->therapist && $schedule->therapist->email) {
            $this->sendNotificationMail($schedule, $schedule->therapist->email, $mailType, $logType, isRecipientStudent: false);
        }

        // Student side: only schedule_email (no student user email or parent/guardian emails)
        $studentEmail = $schedule->student?->studentProfile?->schedule_email;
        if (! $studentEmail) {
            return;
        }

        $this->sendNotificationMail($schedule, $studentEmail, $mailType, $logType, isRecipientStudent: true);
    }

    private function sendNotificationMail(
        Schedule $schedule,
        string $email,
        string $mailType,
        ScheduleEmailType $logType,
        bool $isRecipientStudent,
    ): void {
        try {
            Mail::to($email)->send(new ScheduleNotificationMail($schedule, $mailType, isRecipientStudent: $isRecipientStudent));
            Event::dispatch(new ScheduleEmailSent($schedule->id, $logType, $email));
        } catch (\Throwable $e) {
            $recipient = $isRecipientStudent ? 'student' : 'therapist';
            Log::error("SendScheduleNotification: failed to send {$recipient} mail", [
                'schedule_id' => $schedule->id,
                'type' => $mailType,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
