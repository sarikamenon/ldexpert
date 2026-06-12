<?php

declare(strict_types=1);

namespace App\DTOs\Schedule;

/**
 * One row from the recurring-series occurrence editor: a user-local date plus
 * its (optional) user-local start/end time. When the times are null the caller
 * falls back to the series-level start/end time.
 */
final class OccurrenceInputDTO
{
    public function __construct(
        public readonly string $date,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
    ) {}

    /**
     * Build a list of occurrence inputs from the parallel arrays posted by the form.
     *
     * @param  array<int, string>  $dates
     * @param  array<int, string>|null  $startTimes
     * @param  array<int, string>|null  $endTimes
     * @return array<int, self>
     */
    public static function listFromArrays(array $dates, ?array $startTimes, ?array $endTimes): array
    {
        $inputs = [];

        foreach (array_values($dates) as $index => $date) {
            $cleanDate = str_contains($date, ' ') ? explode(' ', $date)[0] : $date;

            $inputs[] = new self(
                date: $cleanDate,
                startTime: $startTimes[$index] ?? null,
                endTime: $endTimes[$index] ?? null,
            );
        }

        return $inputs;
    }
}
