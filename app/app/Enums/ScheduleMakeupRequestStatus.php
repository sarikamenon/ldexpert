<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleMakeupRequestStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case REQUESTED = 'requested';
    case DECLINED = 'declined';
    case SCHEDULED = 'scheduled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Reminder sent',
            self::REQUESTED => 'Make-up requested',
            self::DECLINED => 'Declined',
            self::SCHEDULED => 'Make-up scheduled',
            self::FAILED => 'Send failed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::DECLINED, self::SCHEDULED, self::FAILED => true,
            default => false,
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
