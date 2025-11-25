<?php

declare(strict_types=1);

namespace App\Enums;

enum TherapistPosition: string
{
    case SLP = 'SLP';
    case OT = 'OT';
    case PT = 'PT';
    case LCSW = 'LCSW';
    case SW = 'SW';
    case BCBA = 'BCBA';
    case RBT = 'RBT';

    public function label(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
