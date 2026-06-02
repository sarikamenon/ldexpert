<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Illuminate\Support\Collection;

final class EloquentScheduleMakeupAvailabilityRepository implements ScheduleMakeupAvailabilityRepositoryInterface
{
    /**
     * @return Collection<int, ScheduleMakeupAvailability>
     */
    public function listUpcomingForTherapist(User $therapist): Collection
    {
        return ScheduleMakeupAvailability::query()
            ->forTherapist($therapist)
            ->upcomingFromToday()
            ->orderBy('availability_date')
            ->orderBy('start_time')
            ->get();
    }

    public function create(User $therapist, string $date, string $startTime, string $endTime, ?string $notes): ScheduleMakeupAvailability
    {
        return ScheduleMakeupAvailability::create([
            'therapist_id' => $therapist->id,
            'availability_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'notes' => $notes,
        ]);
    }

    public function delete(ScheduleMakeupAvailability $window): void
    {
        $window->delete();
    }

    public function therapistHasAvailabilityFromDate(User $therapist, string $fromDate): bool
    {
        return ScheduleMakeupAvailability::query()
            ->forTherapist($therapist)
            ->whereDate('availability_date', '>=', $fromDate)
            ->exists();
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function schedulesOverlappingWindow(ScheduleMakeupAvailability $window): Collection
    {
        return Schedule::query()
            ->forTherapistOwned($window->therapist_id)
            ->withStatuses([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED])
            ->overlappingWindow(
                $window->startUtc()->format('Y-m-d H:i:s'),
                $window->endUtc()->format('Y-m-d H:i:s'),
            )
            ->orderByRaw('TIMESTAMP(schedule_date, start_time)')
            ->get();
    }

    /**
     * @return Collection<int, ScheduleMakeupAvailability>
     */
    public function windowsForTherapistFromDate(User $therapist, string $fromDate): Collection
    {
        return ScheduleMakeupAvailability::query()
            ->forTherapist($therapist)
            ->whereDate('availability_date', '>=', $fromDate)
            ->orderBy('availability_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  Collection<int, ScheduleMakeupAvailability>  $windows
     * @return Collection<int, Schedule>
     */
    public function busySchedulesForWindows(User $therapist, Collection $windows): Collection
    {
        if ($windows->isEmpty()) {
            return new Collection;
        }

        $query = Schedule::query()
            ->forTherapistOwned($therapist->id)
            ->withStatuses([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED]);

        $query->where(function ($q) use ($windows): void {
            foreach ($windows as $window) {
                $q->orWhere(function ($sub) use ($window): void {
                    $sub->where(function ($inner) use ($window): void {
                        /** @var \Illuminate\Database\Eloquent\Builder<Schedule> $inner */
                        $inner->overlappingWindow(
                            $window->startUtc()->format('Y-m-d H:i:s'),
                            $window->endUtc()->format('Y-m-d H:i:s'),
                        );
                    });
                });
            }
        });

        return $query
            ->orderByRaw('TIMESTAMP(schedule_date, start_time)')
            ->get();
    }

    /**
     * @param  array<int, string>  $dates
     */
    public function lockTherapistSchedulesForDates(User $therapist, array $dates): void
    {
        if ($dates === []) {
            return;
        }

        Schedule::query()
            ->forTherapistOwned($therapist->id)
            ->whereIn('schedule_date', array_values(array_unique($dates)))
            ->lockForUpdate()
            ->get();
    }
}
