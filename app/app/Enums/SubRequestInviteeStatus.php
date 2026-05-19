<?php

declare(strict_types=1);

namespace App\Enums;

enum SubRequestInviteeStatus: string
{
    case INVITED = 'invited';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case WITHDRAWN = 'withdrawn';
    case SUPERSEDED = 'superseded';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::WITHDRAWN => 'Withdrawn',
            self::SUPERSEDED => 'Superseded',
            self::EXPIRED => 'Expired',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::INVITED => false,
            default => true,
        };
    }
}
