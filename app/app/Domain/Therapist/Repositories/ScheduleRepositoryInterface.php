<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\OverlapCheckDTO;
use App\DTOs\OverlapExclusionsDTO;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\BillingStatus;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface ScheduleRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, Schedule>}
     */
    public function listForDataTablesForStudent(User $student, ScheduleFilterDTO $filters, DataTablesParamsDTO $params): array;

    /** @return Collection<int, Schedule> */
    public function getSchedulesForTherapist(User $therapist, ScheduleFilterDTO $filters): Collection;

    public function getPendingCount(User $therapist): int;

    /** @return EloquentCollection<int, Schedule> */
    public function getPendingSchedules(User $therapist, ?ScheduleFilterDTO $filters = null): EloquentCollection;

    /** @return Collection<int, \App\Models\School> */
    public function getSchoolsForTherapist(User $therapist): Collection;

    /** @return Collection<int, User> */
    public function getStudentsForTherapist(User $therapist): Collection;

    /** @return Collection<int, array<string, mixed>> */
    public function getStudentServiceMappings(User $therapist): Collection;

    /** @param array<int, int> $studentIds */
    public function validateStudentsShareService(User $therapist, array $studentIds, int $serviceId): bool;

    /** @param array<string, mixed> $data */
    public function create(array $data): Schedule;

    /** @param array<string, mixed> $data */
    public function update(Schedule $schedule, array $data): Schedule;

    public function delete(Schedule $schedule): void;

    public function findForTherapist(User $therapist, int $scheduleId): ?Schedule;

    /** @param array<int, string> $relations */
    public function findById(int $scheduleId, array $relations = []): ?Schedule;

    /** @return Collection<int, Schedule> */
    public function getRecurringOccurrences(Schedule $parentSchedule): Collection;

    /** @return Collection<int, Schedule> */
    public function getRecurringOccurrencesByBatch(string $recurringBatchNumber): Collection;

    /** @return Collection<int, Schedule> */
    public function getUnbilledFutureRecurringOccurrencesByBatch(string $recurringBatchNumber, string $fromDate): Collection;

    /**
     * Future, scheduled, unbilled sessions owned by the therapist (excludes sessions
     * they merely cover as a sub). "Future" is the combined schedule_date + start_time
     * UTC instant compared against the given moment.
     *
     * @return Collection<int, Schedule>
     */
    public function getFutureScheduledForTherapistOwned(int $therapistId, CarbonInterface $now): Collection;

    /** @return Collection<int, Schedule> */
    public function getGroupSchedulesByBatch(string $groupBatchNumber): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Schedule>
     */
    public function getSchedulesForStudent(User $student, array $filters = []): Collection;

    /** @return LengthAwarePaginator<int, Schedule> */
    public function paginateForStudent(User $student, ScheduleFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool;

    /** @param array<int, int> $studentIds */
    public function validateTherapistAccessToStudents(User $therapist, array $studentIds): bool;

    public function generateBatchNumber(string $type = 'recurring'): string;

    public function updateBillingStatus(Schedule $schedule, BillingStatus $status): Schedule;

    /** @param array<int, int> $scheduleIds */
    public function bulkUpdateBillingStatus(array $scheduleIds, BillingStatus $status): int;

    public function hasOverlap(User $user, OverlapCheckDTO $check, OverlapExclusionsDTO $exclusions): bool;

    /**
     * @return Collection<int, Schedule>
     */
    public function getSchedulesInWindow(Carbon $start, Carbon $end): Collection;

    public function countLessonsThisWeek(User $therapist, Carbon $startOfWeek, Carbon $endOfWeek): int;

    /** @return Collection<int, Schedule> */
    public function getSchedulesForCalendar(ScheduleFilterDTO $filters): Collection;
}
