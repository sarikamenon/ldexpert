<?php

declare(strict_types=1);

namespace App\Enums;

enum EmployeeType: string
{
    case W2 = 'W2';
    case CONTRACTOR_1099 = '1099';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
