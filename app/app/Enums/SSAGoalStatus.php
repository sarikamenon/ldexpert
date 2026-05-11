<?php

declare(strict_types=1);

namespace App\Enums;

enum SSAGoalStatus: string
{
    case ACTIVE = 'active';
    case MASTERED = 'mastered';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::MASTERED => 'Mastered',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::ACTIVE => 'info',
            self::MASTERED => 'success',
            self::DISCONTINUED => 'muted',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
