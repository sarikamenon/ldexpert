<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingScheduleRunStatus: string
{
    case SUCCESS = 'success';
    case SKIPPED_NO_SESSIONS = 'skipped_no_sessions';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::SKIPPED_NO_SESSIONS => 'Skipped — No Sessions',
            self::FAILED => 'Failed',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
