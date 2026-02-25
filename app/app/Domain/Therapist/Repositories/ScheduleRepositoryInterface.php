<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\BillingStatus;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ScheduleRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, Schedule>}
     */
    public function listForDataTablesForStudent(User $student, ScheduleFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function getSchedulesForTherapist(User $therapist, ScheduleFilterDTO $filters): Collection;

    public function getPendingCount(User $therapist): int;

    public function getPendingSchedules(User $therapist, ?ScheduleFilterDTO $filters = null): Collection;

    public function getSchoolsForTherapist(User $therapist): Collection;

    public function getStudentsForTherapist(User $therapist): Collection;

    public function getStudentServiceMappings(User $therapist): Collection;

    public function validateStudentsShareService(User $therapist, array $studentIds, int $serviceId): bool;

    public function create(array $data): Schedule;

    public function update(Schedule $schedule, array $data): Schedule;

    public function delete(Schedule $schedule): void;

    public function findForTherapist(User $therapist, int $scheduleId): ?Schedule;

    public function getRecurringOccurrences(Schedule $parentSchedule): Collection;

    public function getRecurringOccurrencesByBatch(string $recurringBatchNumber): Collection;

    public function getGroupSchedulesByBatch(string $groupBatchNumber): Collection;

    public function getSchedulesForStudent(User $student, array $filters = []): Collection;

    public function paginateForStudent(User $student, ScheduleFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool;

    public function validateTherapistAccessToStudents(User $therapist, array $studentIds): bool;

    public function generateBatchNumber(string $type = 'recurring'): string;

    public function updateBillingStatus(Schedule $schedule, BillingStatus $status): Schedule;

    public function bulkUpdateBillingStatus(array $scheduleIds, BillingStatus $status): int;

    public function hasOverlap(User $user, string $date, string $startTime, string $endTime, ?int $excludeScheduleId = null): bool;

    /**
     * @return Collection<int, Schedule>
     */
    public function getSchedulesInWindow(Carbon $start, Carbon $end): Collection;

    public function countLessonsThisWeek(User $therapist, Carbon $startOfWeek, Carbon $endOfWeek): int;
}
