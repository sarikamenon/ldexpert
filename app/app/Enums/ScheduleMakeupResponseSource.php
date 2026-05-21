<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleMakeupResponseSource: string
{
    case EMAIL_LINK = 'email_link';
    case THERAPIST_MANUAL = 'therapist_manual';
    case AUTO_DECLINED = 'auto_declined';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL_LINK => 'Email link',
            self::THERAPIST_MANUAL => 'Therapist (manual)',
            self::AUTO_DECLINED => 'Auto-declined by system',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $src): string => $src->value,
            self::cases()
        );
    }
}
