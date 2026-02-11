<?php

declare(strict_types=1);

namespace App\Enums;

enum SchoolCalendarEventType: string
{
    case HOLIDAY = 'holiday';
    case NON_HOLIDAY = 'non_holiday';

    public function label(): string
    {
        return match ($this) {
            self::HOLIDAY => 'Holiday',
            self::NON_HOLIDAY => 'Non-Holiday',
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
