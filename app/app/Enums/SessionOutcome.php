<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionOutcome: string
{
    case SERVICE_DELIVERED = 'service_delivered';
    case NO_SHOW_STUDENT = 'no_show_student';
    case NO_SHOW_THERAPIST = 'no_show_therapist';
    case TECHNICAL_ISSUE = 'technical_issue';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE_DELIVERED => 'Service delivered',
            self::NO_SHOW_STUDENT => 'No show student',
            self::NO_SHOW_THERAPIST => 'No show therapist',
            self::TECHNICAL_ISSUE => 'Technical issue',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $outcome): string => $outcome->value,
            self::cases()
        );
    }

    public function isBillableForTherapist(): bool
    {
        return match ($this) {
            self::SERVICE_DELIVERED => true,
            self::NO_SHOW_STUDENT => true,
            self::NO_SHOW_THERAPIST => true,
            self::TECHNICAL_ISSUE => true,
        };
    }

    public function isBillableForSchool(): bool
    {
        return match ($this) {
            self::SERVICE_DELIVERED => true,
            self::NO_SHOW_STUDENT => true,
            self::NO_SHOW_THERAPIST => false,
            self::TECHNICAL_ISSUE => false,
        };
    }

    public function shouldIncludeInTho(): bool
    {
        return match ($this) {
            self::SERVICE_DELIVERED => true,
            self::NO_SHOW_STUDENT => true,
            self::NO_SHOW_THERAPIST => false,
            self::TECHNICAL_ISSUE => false,
        };
    }
}
