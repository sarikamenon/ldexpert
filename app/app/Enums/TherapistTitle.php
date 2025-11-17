<?php

declare(strict_types=1);

namespace App\Enums;

enum TherapistTitle: string
{
    case MR = 'Mr.';
    case MRS = 'Mrs.';
    case MS = 'Ms.';
    case DR = 'Dr.';
    case PROF = 'Prof.';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
