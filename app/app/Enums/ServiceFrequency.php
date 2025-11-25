<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceFrequency: string
{
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Weekly',
            self::BI_WEEKLY => 'Bi-weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
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
