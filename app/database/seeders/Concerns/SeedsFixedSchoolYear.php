<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Carbon\Carbon;

trait SeedsFixedSchoolYear
{
    /**
     * Fixed school year for 2025 scenario: Aug 1, 2025 – Jul 31, 2026.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function fixedSchoolYear2025(): array
    {
        return [
            'start' => Carbon::create(2025, 8, 1)->startOfDay(),
            'end' => Carbon::create(2026, 7, 31)->endOfDay(),
        ];
    }
}
