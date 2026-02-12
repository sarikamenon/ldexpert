<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DateHelper
{
    public static function daysDifferenceBetweenDates(
        CarbonInterface|string|null $from,
        CarbonInterface|string|null $to,
    ): ?string {
        if ($from === null || $to === null) {
            return null;
        }

        $fromDate = $from instanceof CarbonInterface ? $from : Carbon::parse($from);
        $toDate = $to instanceof CarbonInterface ? $to : Carbon::parse($to);

        $fromDay = $fromDate->copy()->startOfDay();
        $toDay = $toDate->copy()->startOfDay();

        if ($fromDay->equalTo($toDay)) {
            return null;
        }

        $diffInDays = $fromDay->diffInDays($toDay);
        $dayLabel = $diffInDays === 1 ? 'day' : 'days';

        return "{$diffInDays} {$dayLabel}";
    }
}
