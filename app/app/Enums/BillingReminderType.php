<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingReminderType: string
{
    case UPCOMING_DUE = 'upcoming_due';
    case OVERDUE = 'overdue';
    case OVERDUE_FOLLOWUP = 'overdue_followup';

    public function label(): string
    {
        return match ($this) {
            self::UPCOMING_DUE => 'Upcoming Due',
            self::OVERDUE => 'Overdue',
            self::OVERDUE_FOLLOWUP => 'Overdue Follow-up',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
