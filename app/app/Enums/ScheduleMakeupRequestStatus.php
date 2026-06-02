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
     * Statuses that block deleting the originating scheduled session: the
     * reminder is out (sent), the parent accepted (requested), or a make-up
     * has been booked (scheduled). Deleting the schedule under any of these
     * would orphan an in-flight or committed make-up.
     *
     * @return array<int, string>
     */
    public static function blockingScheduleDeletionValues(): array
    {
        return [
            self::SENT->value,
            self::REQUESTED->value,
            self::SCHEDULED->value,
        ];
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
