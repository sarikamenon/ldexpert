<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use Carbon\CarbonImmutable;

/**
 * Pure interval math for the "windows − schedules → valid 15-min starts" computation.
 *
 * All instants are UTC. No DB access — callers load the therapist's availability
 * windows and busy schedules and pass them in as [start, end] CarbonImmutable pairs.
 */
final class MakeupSlotCalculator
{
    private const STEP_MINUTES = 15;

    /**
     * Free intervals = (union of windows) minus (union of busy intervals).
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function freeIntervals(array $windows, array $busy): array
    {
        $merged = $this->mergeIntervals($windows);
        $blocked = $this->mergeIntervals($busy);

        $free = [];
        foreach ($merged as [$wStart, $wEnd]) {
            $free = array_merge($free, $this->subtractFromInterval($wStart, $wEnd, $blocked));
        }

        return $free;
    }

    /**
     * Valid sub-slot start times at 15-min granularity where start + duration
     * still fits inside a free interval.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     * @return array<int, CarbonImmutable>
     */
    public function validStartTimes(array $windows, array $busy, int $durationMinutes): array
    {
        if ($durationMinutes <= 0) {
            return [];
        }

        $starts = [];
        foreach ($this->freeIntervals($windows, $busy) as [$start, $end]) {
            $cursor = $this->ceilToStep($start);

            while ($cursor->addMinutes($durationMinutes)->lessThanOrEqualTo($end)) {
                $starts[] = $cursor;
                $cursor = $cursor->addMinutes(self::STEP_MINUTES);
            }
        }

        return $starts;
    }

    /**
     * Sort + coalesce overlapping/adjacent intervals into a disjoint set.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function mergeIntervals(array $intervals): array
    {
        $valid = array_values(array_filter(
            $intervals,
            static fn (array $i): bool => $i[0]->lessThan($i[1]),
        ));

        usort($valid, static fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());

        $merged = [];
        foreach ($valid as [$start, $end]) {
            if ($merged === []) {
                $merged[] = [$start, $end];

                continue;
            }

            $lastIndex = count($merged) - 1;
            [$lastStart, $lastEnd] = $merged[$lastIndex];

            if ($start->lessThanOrEqualTo($lastEnd)) {
                $merged[$lastIndex] = [$lastStart, $end->greaterThan($lastEnd) ? $end : $lastEnd];

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }

    /**
     * Subtract a set of (already-merged) blocked intervals from a single interval.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $blocked
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtractFromInterval(CarbonImmutable $start, CarbonImmutable $end, array $blocked): array
    {
        $free = [];
        $cursor = $start;

        foreach ($blocked as [$bStart, $bEnd]) {
            if ($bEnd->lessThanOrEqualTo($cursor) || $bStart->greaterThanOrEqualTo($end)) {
                continue;
            }

            if ($bStart->greaterThan($cursor)) {
                $free[] = [$cursor, $bStart];
            }

            if ($bEnd->greaterThan($cursor)) {
                $cursor = $bEnd;
            }
        }

        if ($cursor->lessThan($end)) {
            $free[] = [$cursor, $end];
        }

        return $free;
    }

    private function ceilToStep(CarbonImmutable $time): CarbonImmutable
    {
        $remainder = (int) $time->minute % self::STEP_MINUTES;
        $base = $time->seconds(0);

        if ($remainder === 0 && (int) $time->second === 0) {
            return $base;
        }

        return $base->addMinutes(self::STEP_MINUTES - $remainder);
    }
}
