<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Repositories;

use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Illuminate\Support\Collection;

interface ScheduleMakeupAvailabilityRepositoryInterface
{
    /**
     * Upcoming (today onwards) windows for the therapist, ordered by date then start_time.
     *
     * @return Collection<int, ScheduleMakeupAvailability>
     */
    public function listUpcomingForTherapist(User $therapist): Collection;

    public function create(User $therapist, string $date, string $startTime, string $endTime, ?string $notes): ScheduleMakeupAvailability;

    public function delete(ScheduleMakeupAvailability $window): void;

    /**
     * Schedules whose UTC interval overlaps the given availability window.
     * Used to derive which sub-slots within the window are already booked.
     *
     * @return Collection<int, Schedule>
     */
    public function schedulesOverlappingWindow(ScheduleMakeupAvailability $window): Collection;

    /**
     * Whether the therapist has at least one availability window on any of the given dates.
     *
     * @param  array<int, string>  $dates  Y-m-d date strings
     */
    public function therapistHasAvailabilityForDates(User $therapist, array $dates): bool;

    /**
     * Availability windows for the therapist on the given dates, ordered by date then start_time.
     *
     * @param  array<int, string>  $dates  Y-m-d date strings
     * @return Collection<int, ScheduleMakeupAvailability>
     */
    public function windowsForTherapistOnDates(User $therapist, array $dates): Collection;

    /**
     * Therapist's non-cancelled schedules that overlap any of the given availability windows.
     * Used by MakeupSlotCalculator as the "busy" set.
     *
     * @param  Collection<int, ScheduleMakeupAvailability>  $windows
     * @return Collection<int, Schedule>
     */
    public function busySchedulesForWindows(User $therapist, Collection $windows): Collection;
}
