<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentScheduleMakeupAvailabilityRepository implements ScheduleMakeupAvailabilityRepositoryInterface
{
    /**
     * @return Collection<int, ScheduleMakeupAvailability>
     */
    public function listUpcomingForTherapist(User $therapist): Collection
    {
        return ScheduleMakeupAvailability::query()
            ->where('therapist_id', $therapist->id)
            ->where('availability_date', '>=', DB::raw('CURDATE()'))
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
}
