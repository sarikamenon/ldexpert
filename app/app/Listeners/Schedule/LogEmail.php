<?php

declare(strict_types=1);

namespace App\Listeners\Schedule;

use App\Events\Schedule\EmailSent;
use App\Models\ScheduleEmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogEmail implements ShouldQueue
{
    public function handle(EmailSent $event): void
    {
        ScheduleEmailLog::create([
            'schedule_id' => $event->scheduleId,
            'type' => $event->type->value,
            'recipient_email' => $event->recipientEmail,
            'sent_by_id' => null,
            'sent_at' => now(),
        ]);
    }
}
