<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\School\Repositories\SchoolCalendarEventRepositoryInterface;
use App\DTOs\CreateSchoolCalendarEventDTO;
use App\DTOs\UpdateSchoolCalendarEventDTO;
use App\Enums\SchoolCalendarEventType;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentSchoolCalendarEventRepository implements SchoolCalendarEventRepositoryInterface
{
    /** @return Collection<int, SchoolCalendarEvent> */
    public function listBySchoolAndRange(int $schoolId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return SchoolCalendarEvent::query()
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get();
    }

    /** @return Collection<int, SchoolCalendarEvent> */
    public function listBySchoolsAndRange(array $schoolIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        if (count($schoolIds) === 0) {
            return collect();
        }

        return SchoolCalendarEvent::query()
            ->whereIn('school_id', $schoolIds)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get();
    }

    public function hasHolidayOnDate(int $schoolId, CarbonInterface $date): bool
    {
        return SchoolCalendarEvent::query()
            ->where('school_id', $schoolId)
            ->where('event_type', SchoolCalendarEventType::HOLIDAY->value)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }

    public function create(CreateSchoolCalendarEventDTO $dto): SchoolCalendarEvent
    {
        return SchoolCalendarEvent::create($dto->toArray());
    }

    public function update(SchoolCalendarEvent $event, UpdateSchoolCalendarEventDTO $dto): SchoolCalendarEvent
    {
        $event->update($dto->toArray());

        return $event->refresh();
    }

    public function delete(SchoolCalendarEvent $event): void
    {
        $event->delete();
    }

    public function find(int $id): ?SchoolCalendarEvent
    {
        return SchoolCalendarEvent::find($id);
    }
}
