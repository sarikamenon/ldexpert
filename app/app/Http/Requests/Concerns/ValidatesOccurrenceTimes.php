<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesOccurrenceTimes
{
    /**
     * Per-occurrence start/end times are parallel arrays to occurrence_dates.
     * When present they must align one-to-one with the dates, come as a pair,
     * and each end time must be after its start time.
     */
    private function validateOccurrenceTimeRules(Validator $validator): void
    {
        $startTimes = $this->input('occurrence_start_times');
        $endTimes = $this->input('occurrence_end_times');

        if (! is_array($startTimes) && ! is_array($endTimes)) {
            return;
        }

        if (! is_array($startTimes) || ! is_array($endTimes)) {
            $validator->errors()->add('occurrence_start_times', 'Occurrence start and end times must be provided together.');

            return;
        }

        $dates = $this->input('occurrence_dates');
        $dateCount = is_array($dates) ? count($dates) : 0;

        if (count($startTimes) !== $dateCount || count($endTimes) !== $dateCount) {
            $validator->errors()->add('occurrence_start_times', 'Each occurrence date must have a matching start and end time.');

            return;
        }

        foreach ($startTimes as $index => $startTime) {
            $endTime = $endTimes[$index] ?? null;
            if (! is_string($startTime) || ! is_string($endTime)) {
                continue;
            }

            if ($endTime <= $startTime) {
                $validator->errors()->add(
                    "occurrence_end_times.{$index}",
                    'Occurrence end time must be after its start time.',
                );
            }
        }
    }
}
