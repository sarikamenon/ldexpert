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
    case NOT_REQUIRED = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::REQUESTED => 'Requested',
            self::DECLINED => 'Declined',
            self::SCHEDULED => 'Scheduled',
            self::FAILED => 'Send failed',
            self::NOT_REQUIRED => 'Not required',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::DECLINED, self::SCHEDULED, self::FAILED, self::NOT_REQUIRED => true,
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
