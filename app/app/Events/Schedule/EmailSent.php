<?php

declare(strict_types=1);

namespace App\Events\Schedule;

use App\Enums\ScheduleEmailType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $scheduleId,
        public readonly ScheduleEmailType $type,
        public readonly string $recipientEmail,
    ) {}
}
