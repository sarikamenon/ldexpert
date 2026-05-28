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
}
