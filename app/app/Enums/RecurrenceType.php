<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrenceType: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case MONTHLY = 'monthly';
    case CUSTOM_WEEKLY = 'custom_weekly';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::BI_WEEKLY => 'Bi-weekly',
            self::MONTHLY => 'Monthly',
            self::CUSTOM_WEEKLY => 'Custom Weekly (Select Days)',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
