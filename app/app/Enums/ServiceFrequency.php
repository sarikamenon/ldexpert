<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case ADHOC = 'adhoc';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::ADHOC => 'Ad Hoc',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn(self $frequency): string => $frequency->value,
            self::cases()
        );
    }
}
