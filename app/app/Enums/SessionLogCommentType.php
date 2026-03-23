<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionLogCommentType: string
{
    case SENT_BACK = 'sent_back';
    case THERAPIST_REPLY = 'therapist_reply';

    public function label(): string
    {
        return match ($this) {
            self::SENT_BACK => 'Sent back',
            self::THERAPIST_REPLY => 'Therapist reply',
        };
    }
}
