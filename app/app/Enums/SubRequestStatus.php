<?php

declare(strict_types=1);

namespace App\Enums;

enum SubRequestStatus: string
{
    case OPEN = 'open';
    case ACCEPTED = 'accepted';
    case CANCELLED = 'cancelled';
    case WITHDRAWN = 'withdrawn';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::ACCEPTED => 'Accepted',
            self::CANCELLED => 'Cancelled',
            self::WITHDRAWN => 'Withdrawn',
            self::EXPIRED => 'Expired',
        };
    }
}
