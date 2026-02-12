<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Carbon\Carbon;

trait SeedsSchoolYear
{
    /**
     * Current school year (July 1 – June 30) based on today.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function currentSchoolYear(): array
    {
        $today = now();
        $startYear = $today->month >= 7 ? $today->year : $today->year - 1;

        return [
            'start' => Carbon::create($startYear, 7, 1)->startOfDay(),
            'end' => Carbon::create($startYear + 1, 6, 30)->endOfDay(),
        ];
    }
}
