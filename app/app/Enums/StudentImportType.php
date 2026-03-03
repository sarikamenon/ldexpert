<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentImportType: string
{
    case NOVA = 'NOVA';
    case RSM = 'RSM';
    case MARVIN = 'MARVIN';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
