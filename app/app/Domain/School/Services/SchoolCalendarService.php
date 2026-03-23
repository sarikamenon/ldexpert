<?php

declare(strict_types=1);

namespace App\Domain\School\Services;

use App\Domain\School\Repositories\SchoolCalendarEventRepositoryInterface;
use App\DTOs\CreateSchoolCalendarEventDTO;
use App\DTOs\UpdateSchoolCalendarEventDTO;
use App\Enums\SchoolCalendarEventType;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class SchoolCalendarService
{
    public function __construct(
        private readonly SchoolCalendarEventRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, SchoolCalendarEvent> */
    public function listBySchoolAndRange(int $schoolId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->repository->listBySchoolAndRange($schoolId, $start, $end);
    }

    /**
     * @param  array<int>  $schoolIds
     * @return Collection<int, SchoolCalendarEvent>
     */
    public function listBySchoolsAndRange(array $schoolIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->repository->listBySchoolsAndRange($schoolIds, $start, $end);
    }

    public function isHolidayDate(int $schoolId, CarbonInterface $date): bool
    {
        return $this->repository->hasHolidayOnDate($schoolId, $date);
    }

    /** @return Collection<int, SchoolCalendarEvent> */
    public function listHolidayEventsBySchoolAndRange(int $schoolId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->repository
            ->listBySchoolAndRange($schoolId, $start, $end)
            ->filter(static fn (SchoolCalendarEvent $event) => $event->event_type === SchoolCalendarEventType::HOLIDAY);
    }

    public function create(CreateSchoolCalendarEventDTO $dto): SchoolCalendarEvent
    {
        return $this->repository->create($dto);
    }

    public function update(SchoolCalendarEvent $event, UpdateSchoolCalendarEventDTO $dto): SchoolCalendarEvent
    {
        return $this->repository->update($event, $dto);
    }

    public function delete(SchoolCalendarEvent $event): void
    {
        $this->repository->delete($event);
    }
}
