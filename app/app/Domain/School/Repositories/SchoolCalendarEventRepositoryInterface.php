<?php

declare(strict_types=1);

namespace App\Domain\School\Repositories;

use App\DTOs\School\CalendarEvent\CreateSchoolCalendarEventDTO;
use App\DTOs\School\CalendarEvent\UpdateSchoolCalendarEventDTO;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SchoolCalendarEventRepositoryInterface
{
    /** @return Collection<int, SchoolCalendarEvent> */
    public function listBySchoolAndRange(int $schoolId, CarbonInterface $start, CarbonInterface $end): Collection;

    /**
     * @param  array<int>  $schoolIds
     * @return Collection<int, SchoolCalendarEvent>
     */
    public function listBySchoolsAndRange(array $schoolIds, CarbonInterface $start, CarbonInterface $end): Collection;

    public function hasHolidayOnDate(int $schoolId, CarbonInterface $date): bool;

    public function create(CreateSchoolCalendarEventDTO $dto): SchoolCalendarEvent;

    public function update(SchoolCalendarEvent $event, UpdateSchoolCalendarEventDTO $dto): SchoolCalendarEvent;

    public function delete(SchoolCalendarEvent $event): void;

    public function find(int $id): ?SchoolCalendarEvent;
}
