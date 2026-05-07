<?php

declare(strict_types=1);

namespace App\Support;

final class Currency
{
    public static function format(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    public static function formatAbs(float $amount): string
    {
        return '$'.number_format(abs($amount), 2);
    }
}
