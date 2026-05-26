<?php

declare(strict_types=1);

namespace App\Listeners\Schedule;

use App\Enums\ScheduleEmailType;
use App\Events\Schedule\Created;
use App\Events\Schedule\EmailSent;
use App\Events\Schedule\Updated;
use App\Mail\ScheduleNotificationMail;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotification implements ShouldQueue
{
    public function handle(Created|Updated $event): void
    {
        $schedule = $event->schedule;

        // Skip notifications for past-date schedules created retroactively
        if ($schedule->schedule_date->startOfDay()->lt(Carbon::today())) {
            return;
        }

        // Eager load relationships if missing to avoid N+1 in loop/mail view
        $schedule->loadMissing(['therapist', 'student.studentProfile', 'service']);

        $isCreated = $event instanceof Created;
        $mailType = $isCreated ? 'created' : 'updated';
        $logType = $isCreated
            ? ScheduleEmailType::NOTIFICATION_CREATED
            : ScheduleEmailType::NOTIFICATION_UPDATED;

        if ($schedule->service && ! $schedule->service->allowsScheduleEmail()) {
            return;
        }

        // Only notify student schedule contact — therapists do not receive schedule emails
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
            Event::dispatch(new EmailSent($schedule->id, $logType, $email));
        } catch (\Throwable $e) {
            $recipient = $isRecipientStudent ? 'student' : 'therapist';
            Log::error("SendNotification: failed to send {$recipient} mail", [
                'schedule_id' => $schedule->id,
                'type' => $mailType,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
