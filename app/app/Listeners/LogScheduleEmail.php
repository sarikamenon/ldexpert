<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ScheduleEmailSent;
use App\Models\ScheduleEmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogScheduleEmail implements ShouldQueue
{
    public function handle(ScheduleEmailSent $event): void
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
