<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingFrequency: string
{
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case SEMI_MONTHLY = 'semi_monthly';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Weekly',
            self::BI_WEEKLY => 'Bi-Weekly',
            self::SEMI_MONTHLY => 'Semi-Monthly',
            self::MONTHLY => 'Monthly',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $frequency): string => $frequency->value,
            self::cases()
        );
    }
}
