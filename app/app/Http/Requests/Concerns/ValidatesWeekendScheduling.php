<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Enums\WeekDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

trait ValidatesWeekendScheduling
{
    protected function schoolAllowsWeekendScheduling(?int $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        return app(SchoolRepositoryInterface::class)->find($schoolId)?->allow_weekend_scheduling === true;
    }

    /**
     * Add weekend-scheduling errors for schedule_date, weekly_days, and occurrence_dates
     * when the school disallows weekend scheduling. No-op when weekends are permitted.
     *
     * @param  array<int, string|null>|null  $occurrenceDates
     */
    protected function addWeekendSchedulingErrors(
        Validator $validator,
        bool $allowsWeekend,
        ?string $scheduleDate,
        mixed $weeklyDays,
        ?array $occurrenceDates,
    ): void {
        if ($allowsWeekend) {
            return;
        }

        if ($scheduleDate && $this->isWeekendDate($scheduleDate)) {
            $validator->errors()->add('schedule_date', 'Schedules are not allowed on weekends for this school.');
        }

        $hasWeekendDay = Collection::wrap($weeklyDays)
            ->filter(fn ($value): bool => is_string($value) && WeekDay::tryFrom($value)?->isWeekend() === true)
            ->isNotEmpty();

        if ($hasWeekendDay) {
            $validator->errors()->add('weekly_days', 'Saturday and Sunday are not allowed for this school.');
        }

        $weekendDates = Collection::wrap($occurrenceDates)
            ->filter(fn ($dateStr): bool => is_string($dateStr) && $dateStr !== '' && $this->isWeekendDate($dateStr))
            ->map(fn (string $dateStr): string => Carbon::parse($dateStr)->format('M d, Y'));

        if ($weekendDates->isNotEmpty()) {
            $validator->errors()->add(
                'occurrence_dates',
                'The following dates fall on weekends and cannot be scheduled: '.$weekendDates->implode(', ').'. Please adjust these dates.'
            );
        }
    }

    private function isWeekendDate(string $dateStr): bool
    {
        try {
            return Carbon::parse($dateStr)->isWeekend();
        } catch (\Exception) {
            return false;
        }
    }
}
