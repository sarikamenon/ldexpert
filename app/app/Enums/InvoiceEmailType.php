<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceEmailType: string
{
    case INITIAL = 'initial';
    case RESEND = 'resend';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Initial Send',
            self::RESEND => 'Resend',
        };
    }
}
