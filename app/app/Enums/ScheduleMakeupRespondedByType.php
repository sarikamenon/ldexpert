<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleMakeupRespondedByType: string
{
    case PARENT = 'parent';
    case THERAPIST = 'therapist';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::PARENT => 'Parent',
            self::THERAPIST => 'Therapist',
            self::SYSTEM => 'System',
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
