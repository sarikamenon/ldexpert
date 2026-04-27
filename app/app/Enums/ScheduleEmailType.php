<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleEmailType: string
{
    case NOTIFICATION_CREATED = 'notification_created';
    case NOTIFICATION_UPDATED = 'notification_updated';
    case REMINDER_48H = 'reminder_48h';
    case REMINDER_2H = 'reminder_2h';

    public function label(): string
    {
        return match ($this) {
            self::NOTIFICATION_CREATED => 'Schedule Created',
            self::NOTIFICATION_UPDATED => 'Schedule Updated',
            self::REMINDER_48H => '48-Hour Reminder',
            self::REMINDER_2H => '2-Hour Reminder',
        };
    }
}
