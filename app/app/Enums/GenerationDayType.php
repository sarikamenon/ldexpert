<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationDayType: string
{
    case DAY_OF_WEEK = 'day_of_week';
    case FIXED_DELAY = 'fixed_delay';

    public function label(): string
    {
        return match ($this) {
            self::DAY_OF_WEEK => 'Day of Week',
            self::FIXED_DELAY => 'Fixed Delay',
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
