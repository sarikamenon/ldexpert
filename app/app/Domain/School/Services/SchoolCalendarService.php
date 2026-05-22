<?php

declare(strict_types=1);

namespace App\Domain\School\Services;

use App\Domain\School\Repositories\SchoolCalendarEventRepositoryInterface;
use App\DTOs\School\CalendarEvent\CreateSchoolCalendarEventDTO;
use App\DTOs\School\CalendarEvent\SchoolCalendarEventResponseDTO;
use App\DTOs\School\CalendarEvent\UpdateSchoolCalendarEventDTO;
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

    /** @return Collection<int, SchoolCalendarEventResponseDTO> */
    public function listBySchoolAndRangeAsDTO(int $schoolId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->listBySchoolAndRange($schoolId, $start, $end)
            ->map(static fn (SchoolCalendarEvent $event): SchoolCalendarEventResponseDTO => SchoolCalendarEventResponseDTO::fromModel($event))
            ->values();
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

    /**
     * Expand every holiday event in the range into its constituent Y-m-d
     * date strings (multi-day holidays produce one entry per day). Used by
     * the therapist scheduling form to render an inline warning list.
     *
     * @return array<int, string>
     */
    public function listHolidayDateStringsForSchool(int $schoolId, CarbonInterface $start, CarbonInterface $end): array
    {
        return $this->listHolidayEventsBySchoolAndRange($schoolId, $start, $end)
            ->flatMap(static fn (SchoolCalendarEvent $event): Collection => Collection::make(iterator_to_array($event->start_date->daysUntil($event->end_date), false))
                ->map(static fn (CarbonInterface $day): string => $day->format('Y-m-d')))
            ->unique()
            ->values()
            ->all();
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
