<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingMode: string
{
    case STANDARD = 'standard';
    case ADVANCE = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard',
            self::ADVANCE => 'Advance',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $mode): string => $mode->value,
            self::cases()
        );
    }
}
