<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\OverlapCheckDTO;
use App\DTOs\OverlapExclusionsDTO;
use App\DTOs\Schedule\OccurrenceInputDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Events\Schedule\Created;
use App\Events\Schedule\Updated;
use App\Exceptions\CannotDeleteBilledScheduleException;
use App\Exceptions\ScheduleOverlapException;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ScheduleService
{
    /**
     * Per-instance cache of school_id => is_billable.
     * Safe because services are resolved once per request (singleton in the container),
     * so the cache never outlives a single HTTP request or queue job.
     *
     * @var array<int, bool>
     */
    private array $schoolBillableCache = [];

    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly UserTimezoneService $timezoneService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly OccurrenceSyncService $occurrenceSyncService,
    ) {}

    /**
     * Resolve whether schedules under the given school should be flagged as billable.
     * Inverse of the school's non_billable_scheduling flag.
     */
    private function isSchoolBillable(int $schoolId): bool
    {
        if (! array_key_exists($schoolId, $this->schoolBillableCache)) {
            $school = $this->schoolRepository->find($schoolId);
            $this->schoolBillableCache[$schoolId] = $school === null
                ? true
                : ! $school->non_billable_scheduling;
        }

        return $this->schoolBillableCache[$schoolId];
    }

    private function schoolAllowsWeekendScheduling(Schedule $parentSchedule): bool
    {
        $schoolId = $parentSchedule->school_id
            ?? $this->studentRepository->getSchoolIdByUserId((int) $parentSchedule->student_id);

        if (! $schoolId) {
            return false;
        }

        $school = $this->schoolRepository->find($schoolId);

        return $school?->allow_weekend_scheduling === true;
    }

    /** @return Collection<int, Schedule> */
    public function getSchedules(User $therapist, ScheduleFilterDTO $filters): Collection
    {
        return $this->repository->getSchedulesForTherapist($therapist, $filters);
    }

    /** @return Collection<int, Schedule> */
    public function getSchedulesForCalendar(ScheduleFilterDTO $filters): Collection
    {
        return $this->repository->getSchedulesForCalendar($filters);
    }

    public function findForTherapist(User $therapist, int $scheduleId): ?Schedule
    {
        return $this->repository->findForTherapist($therapist, $scheduleId);
    }

    /**
     * Find any schedule by id regardless of owning therapist (admin context).
     *
     * @param  array<int, string>  $relations
     */
    public function findById(int $scheduleId, array $relations = []): ?Schedule
    {
        return $this->repository->findById($scheduleId, $relations);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findForTherapistWithRelations(User $therapist, int $scheduleId, array $relations = []): ?Schedule
    {
        $schedule = $this->repository->findForTherapist($therapist, $scheduleId);

        if ($schedule && ! empty($relations)) {
            $schedule->load($relations);
        }

        return $schedule;
    }

    public function getPendingCount(User $therapist): int
    {
        return $this->repository->getPendingCount($therapist);
    }

    /** @return EloquentCollection<int, Schedule> */
    public function getPendingSchedules(User $therapist, ?ScheduleFilterDTO $filters = null): EloquentCollection
    {
        return $this->repository->getPendingSchedules($therapist, $filters);
    }

    /** @return LengthAwarePaginator<int, Schedule> */
    public function paginateForStudent(User $student, ScheduleFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForStudent($student, $filters, $perPage);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Support\Collection<int, \App\Models\Schedule>}
     */
    public function listForDataTablesForStudent(User $student, ScheduleFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTablesForStudent($student, $filters, $params);
    }

    /** @return Collection<int, \App\Models\School> */
    public function getSchools(User $therapist): Collection
    {
        return $this->repository->getSchoolsForTherapist($therapist);
    }

    /** @return Collection<int, User> */
    public function getStudents(User $therapist): Collection
    {
        return $this->repository->getStudentsForTherapist($therapist);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getStudentServiceMappings(User $therapist): Collection
    {
        return $this->repository->getStudentServiceMappings($therapist);
    }

    public function createSchedule(User $therapist, CreateScheduleDTO $dto, ?int $actorId = null): Schedule
    {
        return DB::transaction(function () use ($therapist, $dto, $actorId): Schedule {
            // Access validation (in addition to FormRequest)
            if ($dto->ssaId !== null && ! $this->repository->validateTherapistAccessToSSA($therapist, $dto->ssaId)) {
                throw new \InvalidArgumentException('Therapist does not have access to the selected SSA.');
            }

            if (! $this->repository->validateTherapistAccessToStudents($therapist, $dto->studentIds)) {
                throw new \InvalidArgumentException('Therapist does not have access to one or more selected students.');
            }

            $service = $this->serviceRepository->findOrFail($dto->serviceId);
            if ($service->is_direct_service && ! $this->repository->validateStudentsShareService($therapist, $dto->studentIds, $dto->serviceId)) {
                throw new \InvalidArgumentException('Selected students do not share this service via an active SSA.');
            }

            // Timezone Conversion & Overlap Check
            $localStartStr = $dto->scheduleDate.' '.$dto->startTime;
            $utcStart = $this->timezoneService->parseUserLocalToUtc($localStartStr, $therapist);
            $utcEnd = $utcStart->copy()->addMinutes($dto->durationMinutes);

            // Fetch students before overlap checks (tests expect these to be called)
            $students = $this->userRepository->findByIds($dto->studentIds);

            $overlapCheck = new OverlapCheckDTO(
                $utcStart->toDateString(),
                $utcStart->toTimeString(),
                $utcEnd->toTimeString(),
            );
            $noExclusions = OverlapExclusionsDTO::none();

            // Validate Therapist Overlap
            $this->validateOverlap($therapist, $overlapCheck, $noExclusions);

            // Validate Student Overlap
            foreach ($students as $student) {
                $this->validateOverlap($student, $overlapCheck, $noExclusions);
            }
            $isGroup = $dto->isGroup || $service->is_group_service || count($dto->studentIds) > 1;

            $recurringBatchNumber = $dto->recurrenceType !== RecurrenceType::NONE
                ? $this->repository->generateBatchNumber('recurring')
                : null;

            $recurrenceEndDate = null;
            if ($dto->recurrenceType !== RecurrenceType::NONE) {
                $recurrenceEndDate = $dto->recurrenceEndDate;

                if (! $recurrenceEndDate && $dto->occurrenceCount !== null) {
                    $recurrenceEndDate = $this->calculateRecurrenceEndDate(
                        $dto->scheduleDate,
                        $dto->recurrenceType,
                        $dto->occurrenceCount
                    );
                }

                if (! $recurrenceEndDate) {
                    throw new \InvalidArgumentException('An occurrence count or end date is required for repeating schedules.');
                }
            }

            $schedules = collect();

            if ($dto->recurrenceType === RecurrenceType::NONE) {
                // Single occurrence (non-recurring)
                $groupBatchNumber = $isGroup
                    ? $this->repository->generateBatchNumber('group')
                    : null;

                foreach ($dto->studentIds as $studentId) {
                    $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId)
                        ?? throw new \InvalidArgumentException("Student {$studentId} has no school assigned.");

                    $data = [
                        'therapist_id' => $therapist->id,
                        'student_id' => $studentId,
                        'ssa_id' => $dto->ssaId,
                        'service_id' => $dto->serviceId,
                        'school_id' => $schoolId,
                        'parent_schedule_id' => null,
                        'schedule_date' => $utcStart->toDateString(),
                        'start_time' => $utcStart->toTimeString(),
                        'end_time' => $utcEnd->toTimeString(),
                        'recurrence_type' => RecurrenceType::NONE,
                        'recurrence_end_date' => null,
                        'is_group' => $isGroup,
                        'recurring_batch_number' => null,
                        'group_batch_number' => $groupBatchNumber,
                        'status' => ScheduleStatus::SCHEDULED,
                        'billing_status' => BillingStatus::PENDING,
                        'is_billable' => $this->isSchoolBillable($schoolId),
                        'notes' => $dto->notes,
                        'location_details' => $dto->locationDetails,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ];

                    $schedules->push($this->repository->create($data));
                }
            } else {
                // Recurring schedule: create parent + occurrences
                // Parent schedule (per first student, used to store rules)
                $firstStudentId = $dto->studentIds[0];
                $firstSchoolId = $this->studentRepository->getSchoolIdByUserId($firstStudentId)
                    ?? throw new \InvalidArgumentException("Student {$firstStudentId} has no school assigned.");

                /** @var Schedule $parentSchedule */
                $parentSchedule = $this->repository->create([
                    'therapist_id' => $therapist->id,
                    'student_id' => $firstStudentId,
                    'ssa_id' => $dto->ssaId,
                    'service_id' => $dto->serviceId,
                    'school_id' => $firstSchoolId,
                    'parent_schedule_id' => null,
                    'schedule_date' => $utcStart->toDateString(),
                    'start_time' => $utcStart->toTimeString(),
                    'end_time' => $utcEnd->toTimeString(),
                    'recurrence_type' => $dto->recurrenceType,
                    'recurrence_end_date' => $recurrenceEndDate, // This is likely local date, but for logic we might need UTC date. Storing as is for now.
                    'is_group' => $isGroup,
                    'recurring_batch_number' => $recurringBatchNumber,
                    'group_batch_number' => $isGroup ? $this->repository->generateBatchNumber('group') : null,
                    'status' => ScheduleStatus::SCHEDULED,
                    'billing_status' => BillingStatus::PENDING,
                    'is_billable' => $this->isSchoolBillable($firstSchoolId),
                    'notes' => $dto->notes,
                    'location_details' => $dto->locationDetails,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $schedules->push($parentSchedule);

                // Use provided occurrence dates if available, otherwise generate them
                if ($dto->occurrenceDates !== null && count($dto->occurrenceDates) > 0) {
                    $occurrences = $this->createOccurrencesFromDates($parentSchedule, $dto->occurrenceDates, $dto->scheduleDate, $dto->studentIds, $isGroup, $therapist);
                } else {
                    $occurrences = $this->generateRecurringOccurrences($parentSchedule, $dto->studentIds, $isGroup);
                }
                $schedules = $schedules->merge($occurrences);
            }

            /** @var Schedule $first */
            $first = $schedules->first();

            // Dispatch events
            if ($dto->recurrenceType === RecurrenceType::NONE) {
                foreach ($schedules as $schedule) {
                    Created::dispatch($schedule);
                }
            } else {
                // For recurring, only dispatch for the parent/first occurrence to avoid spam
                Created::dispatch($first);
            }

            return $first;
        });
    }

    public function updateSchedule(User $therapist, int $scheduleId, UpdateScheduleDTO $dto, ?int $actorId = null): Schedule
    {
        return DB::transaction(function () use ($therapist, $scheduleId, $dto, $actorId): Schedule {
            $schedule = $this->repository->findForTherapist($therapist, $scheduleId);

            if (! $schedule) {
                throw new \RuntimeException('Schedule not found for therapist.');
            }

            $data = $dto->toArray();
            unset($data['duration_minutes']);

            // Timezone Conversion & Overlap Check for Updates
            // Need to check if date/time are present in DTO, otherwise use existing schedule values?
            // DTO fromArray sets all fields.

            $durationMinutes = $dto->durationMinutes ?? $schedule->durationMinutes();
            $localStartStr = $dto->scheduleDate.' '.$dto->startTime;
            $utcStart = $this->timezoneService->parseUserLocalToUtc($localStartStr, $therapist);
            $utcEnd = $utcStart->copy()->addMinutes($durationMinutes);

            $incomingRecurrenceType = $dto->recurrenceType ?? $schedule->recurrence_type ?? RecurrenceType::NONE;
            $incomingEndDate = $dto->recurrenceEndDate ?? $schedule->recurrence_end_date?->format('Y-m-d');

            $recurrenceChanged = $dto->recurrenceType !== null
                && $dto->recurrenceType !== $schedule->recurrence_type;
            $endDateChanged = $dto->recurrenceEndDate !== null
                && $dto->recurrenceEndDate !== $schedule->recurrence_end_date?->format('Y-m-d');

            // The occurrence list is posted on every save of a recurring schedule.
            // Treat it as "changed" only when it actually differs from what is
            // stored, so a no-op save no longer rebuilds the whole series.
            $occurrenceDatesChanged = $dto->occurrenceDates !== null
                && $this->occurrenceListDiffersFromBatch($schedule, $dto->occurrenceDates);

            // A full regenerate (delete + recreate all unbilled future occurrences,
            // with a new batch anchor) is warranted when the recurrence RULE
            // changes — i.e. the recurrence type changes, which alters the pattern
            // for every occurrence (the client also flags this via
            // occurrences_regenerated). Extending/shrinking the end date is handled
            // additively below via the sync path instead, preserving existing rows.
            $regenerate = $recurrenceChanged || $dto->occurrencesRegenerated;

            // Otherwise, when an occurrence list is submitted that differs from the
            // DB (dates added/removed via an end-date change, or per-occurrence
            // times edited), reconcile it in place — preserving row ids and any
            // linked session logs. Adding dates (end-date extension) creates only
            // the new rows; removing dates (end-date shrink) deletes only the
            // dropped rows.
            $syncOccurrences = ! $regenerate
                && $dto->occurrenceDates !== null
                && $schedule->recurring_batch_number !== null
                && ($occurrenceDatesChanged || $endDateChanged || $dto->occurrenceStartTimes !== null);

            $recurrenceSettingsChanged = $regenerate;

            // The stored end date still needs updating even on the additive path.
            $persistEndDateOnly = ! $regenerate && $endDateChanged;

            // Validate overlap (exclude current schedule and its entire batch so
            // siblings in the same recurring series don't false-positive against the new time).
            $overlapCheck = new OverlapCheckDTO(
                $utcStart->toDateString(),
                $utcStart->toTimeString(),
                $utcEnd->toTimeString(),
            );
            $exclusions = new OverlapExclusionsDTO(
                scheduleId: $scheduleId,
                batchNumber: $schedule->recurring_batch_number,
            );

            $this->validateOverlap($therapist, $overlapCheck, $exclusions);

            $student = $this->userRepository->findById($schedule->student_id);
            if ($student) {
                $this->validateOverlap($student, $overlapCheck, $exclusions);
            }

            // Update data with UTC values
            $data['schedule_date'] = $utcStart->toDateString();
            $data['start_time'] = $utcStart->toTimeString();
            $data['end_time'] = $utcEnd->toTimeString();

            $effectiveSchoolId = (int) (array_key_exists('school_id', $data) ? $data['school_id'] : $schedule->school_id);
            $data['is_billable'] = $this->isSchoolBillable($effectiveSchoolId);

            $data['updated_by'] = $actorId;

            // When recurrence settings change, delete all unbilled future occurrences from
            // this schedule's date forward (preserving past/billed sessions in the series).
            if ($recurrenceSettingsChanged && $schedule->recurring_batch_number) {
                $this->repository->getUnbilledFutureRecurringOccurrencesByBatch(
                    $schedule->recurring_batch_number,
                    $schedule->schedule_date->format('Y-m-d'),
                )->each(function (Schedule $occurrence) use ($schedule): void {
                    if ($occurrence->id !== $schedule->id) {
                        $this->repository->delete($occurrence);
                    }
                });
            }

            // Following the iCalendar model, an occurrence-scope edit ("Edit this
            // schedule") keeps the row in its series as a modified exception — it
            // changes only this row's date/time/details and never touches the
            // recurrence linkage. So the branches below run only for series-scope
            // recurrence changes.
            if ($incomingRecurrenceType === RecurrenceType::NONE) {
                // Switching to NONE removes recurring fields and clears parent linkage.
                $data['recurrence_type'] = RecurrenceType::NONE->value;
                $data['recurrence_end_date'] = null;
                $data['recurring_batch_number'] = null;
                $data['parent_schedule_id'] = null;
            } elseif ($recurrenceSettingsChanged) {
                // New recurrence rule: this schedule becomes the new series anchor.
                $data['recurrence_type'] = $incomingRecurrenceType->value;
                $data['recurrence_end_date'] = $incomingEndDate;
                $data['recurring_batch_number'] = $this->repository->generateBatchNumber('recurring');
                $data['parent_schedule_id'] = null;
            } elseif ($persistEndDateOnly) {
                // Additive end-date change: keep the batch/anchor, just record the
                // new end date. Existing rows are preserved; the sync path below
                // adds rows beyond the old end or trims rows past a shortened end.
                $data['recurrence_end_date'] = $incomingEndDate;
            }

            $updated = $this->repository->update($schedule, $data);

            // Capture which schedule columns actually changed so we can suppress the
            // student-facing "schedule updated" email when the save only touched
            // sub-coverage metadata (handled via separate flows in ScheduleSubRequestService).
            $changedColumns = array_keys($updated->getChanges());
            $meaningfulChanges = array_diff(
                $changedColumns,
                ['updated_at', 'sub_therapist_id', 'sub_request_status']
            );
            $hasMeaningfulChange = $meaningfulChanges !== [];

            // Regenerate future occurrences when recurrence settings changed.
            if ($recurrenceSettingsChanged && $updated->isRecurring()) {
                $studentIds = [$updated->student_id];
                if ($updated->isGroup()) {
                    $groupStudentIds = $this->repository
                        ->getGroupSchedulesByBatch($updated->group_batch_number ?? '')
                        ->pluck('student_id')
                        ->unique()
                        ->all();

                    if ($groupStudentIds !== []) {
                        $studentIds = $groupStudentIds;
                    }
                }

                if ($dto->occurrenceDates !== null && count($dto->occurrenceDates) > 0) {
                    $this->createOccurrencesFromDates(
                        $updated,
                        $dto->occurrenceDates,
                        $updated->schedule_date->format('Y-m-d'),
                        $studentIds,
                        $updated->isGroup(),
                        $therapist,
                    );
                } else {
                    $this->generateRecurringOccurrences($updated, $studentIds, $updated->isGroup());
                }
            }

            // Reconcile individually-edited occurrences in place (no full rebuild):
            // updates matching rows' times, detaches rows whose date/time leaves the
            // series pattern, deletes removed dates, and creates added ones.
            if ($syncOccurrences && $updated->isRecurring() && $dto->occurrenceDates !== null) {
                $studentIds = [$updated->student_id];
                if ($updated->isGroup()) {
                    $groupStudentIds = $this->repository
                        ->getGroupSchedulesByBatch($updated->group_batch_number ?? '')
                        ->pluck('student_id')
                        ->unique()
                        ->all();

                    if ($groupStudentIds !== []) {
                        $studentIds = $groupStudentIds;
                    }
                }

                $this->occurrenceSyncService->sync(
                    $updated,
                    OccurrenceInputDTO::listFromArrays(
                        $dto->occurrenceDates,
                        $dto->occurrenceStartTimes,
                        $dto->occurrenceEndTimes,
                    ),
                    $studentIds,
                    $updated->isGroup(),
                    $therapist,
                );

                // Shrinking the list (removing dates) can leave the batch
                // mis-structured or down to one session — keep the anchor valid
                // and demote a lone survivor.
                if ($updated->recurring_batch_number !== null) {
                    $this->reanchorBatch($updated->recurring_batch_number);
                    $this->demoteBatchIfSingleRemaining($updated->recurring_batch_number);
                }
            }

            // Update billing status if provided
            if ($dto->billingStatus instanceof BillingStatus) {
                $updated = $this->repository->updateBillingStatus($updated, $dto->billingStatus);
            }

            if ($hasMeaningfulChange) {
                Updated::dispatch($updated);
            }

            return $updated;
        });
    }

    public function deleteSchedule(User $therapist, int $scheduleId): void
    {
        DB::transaction(function () use ($therapist, $scheduleId): void {
            $schedule = $this->repository->findForTherapist($therapist, $scheduleId);

            if (! $schedule) {
                return;
            }

            if ($schedule->billing_status === BillingStatus::BILLED) {
                throw new CannotDeleteBilledScheduleException;
            }

            $batchNumber = $schedule->recurring_batch_number;

            $this->repository->delete($schedule);

            // Deleting a batch row (possibly the anchor) can orphan siblings or
            // leave a single survivor — re-anchor the batch, then demote if one.
            if ($batchNumber !== null) {
                $this->reanchorBatch($batchNumber);
                $this->demoteBatchIfSingleRemaining($batchNumber);
            }
        });
    }

    /**
     * When a recurring batch is reduced to a single remaining (non-deleted)
     * occurrence, that occurrence is no longer part of a series — clear its
     * recurrence metadata so it behaves and displays as a standalone schedule.
     * Billed survivors are left untouched (we never mutate billed rows).
     */
    private function demoteBatchIfSingleRemaining(string $batchNumber): void
    {
        $remaining = $this->repository->getRecurringOccurrencesByBatch($batchNumber);

        if ($remaining->count() !== 1) {
            return;
        }

        $survivor = $remaining->first();

        if ($survivor === null || $survivor->billing_status === BillingStatus::BILLED) {
            return;
        }

        $this->repository->update($survivor, [
            'recurrence_type' => RecurrenceType::NONE->value,
            'recurrence_end_date' => null,
            'recurring_batch_number' => null,
            'parent_schedule_id' => null,
        ]);
    }

    /**
     * Keep a recurring batch's parent/child structure consistent after rows leave
     * it (e.g. the anchor itself was detached or deleted). Ensures exactly one
     * anchor (`parent_schedule_id IS NULL`) remains and every other row points at
     * it, so siblings never reference a row that has left the batch.
     *
     * Idempotent: a no-op when the batch already has a single valid anchor.
     */
    private function reanchorBatch(string $batchNumber): void
    {
        $rows = $this->repository->getRecurringOccurrencesByBatch($batchNumber);

        // 0 rows: nothing to anchor. 1 row: demotion handles collapsing it.
        if ($rows->count() < 2) {
            return;
        }

        $anchors = $rows->whereNull('parent_schedule_id');

        // Already exactly one anchor and all children point at it → nothing to do.
        if ($anchors->count() === 1) {
            $anchorId = $anchors->first()?->id;
            $orphaned = $rows->contains(
                fn (Schedule $s): bool => $s->parent_schedule_id !== null && $s->parent_schedule_id !== $anchorId
            );

            if (! $orphaned) {
                return;
            }
        }

        // Promote the earliest row as the sole anchor; repoint the rest to it.
        // Rows are ordered by schedule_date (then start_time) by the repository.
        $newAnchor = $rows->first();
        if ($newAnchor === null) {
            return;
        }

        $rows->each(function (Schedule $row) use ($newAnchor): void {
            $shouldBeParent = $row->id === $newAnchor->id ? null : $newAnchor->id;

            if ($row->parent_schedule_id !== $shouldBeParent) {
                $this->repository->update($row, ['parent_schedule_id' => $shouldBeParent]);
            }
        });
    }

    public function deleteFutureRecurringSchedules(User $therapist, int $scheduleId): int
    {
        return DB::transaction(function () use ($therapist, $scheduleId): int {
            $schedule = $this->repository->findForTherapist($therapist, $scheduleId);

            if (! $schedule || ! $schedule->recurring_batch_number) {
                return 0;
            }

            $futureSchedules = $this->repository->getUnbilledFutureRecurringOccurrencesByBatch(
                $schedule->recurring_batch_number,
                $schedule->schedule_date->format('Y-m-d'),
            );

            if ($futureSchedules->isEmpty()) {
                return 0;
            }

            $batchNumber = $schedule->recurring_batch_number;

            $futureSchedules->each(fn (Schedule $s) => $this->repository->delete($s));

            $this->reanchorBatch($batchNumber);
            $this->demoteBatchIfSingleRemaining($batchNumber);

            return $futureSchedules->count();
        });
    }

    /**
     * Soft-delete a therapist's future, scheduled, unbilled sessions (used when a
     * therapist is deactivated). Only sessions the therapist owns are removed —
     * sessions they merely cover as a sub are left untouched. Returns the count.
     */
    public function deleteTherapistFutureSchedules(User $therapist): int
    {
        return DB::transaction(function () use ($therapist): int {
            $futureSchedules = $this->repository->getFutureScheduledForTherapistOwned(
                $therapist->id,
                Carbon::now(),
            );

            $futureSchedules->each(fn (Schedule $s) => $this->repository->delete($s));

            return $futureSchedules->count();
        });
    }

    public function removeStudentFromOccurrence(User $therapist, int $scheduleId): void
    {
        DB::transaction(function () use ($therapist, $scheduleId): void {
            $schedule = $this->repository->findForTherapist($therapist, $scheduleId);

            if (! $schedule) {
                return;
            }

            $this->repository->delete($schedule);
        });
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Schedule>
     */
    public function generateRecurringOccurrences(Schedule $parentSchedule, array $studentIds, bool $isGroup): Collection
    {
        if (! $parentSchedule->recurrence_type
            || $parentSchedule->recurrence_type === RecurrenceType::NONE
            || $parentSchedule->recurrence_type === RecurrenceType::CUSTOM_WEEKLY
            || ! $parentSchedule->recurrence_end_date) {
            return collect([]);
        }

        $recurrenceType = $parentSchedule->recurrence_type;

        /** @var User $therapist */
        $therapist = $this->userRepository->findById($parentSchedule->therapist_id);
        $students = $this->userRepository->findByIds($studentIds);

        $occurrences = collect();

        // Parent stores UTC. Convert to therapist-local for calculation so
        // recurrence walks happen in the user's wall-clock time.
        $endDate = $parentSchedule->recurrence_end_date;

        $localStart = $this->timezoneService->toUserTimezone($parentSchedule->startUtc(), $therapist);
        $localEnd = $this->timezoneService->toUserTimezone($parentSchedule->endUtc(), $therapist);

        // Use mutable Carbon for the recurrence walk loop below — the loop
        // calls nextRecurrenceDate() which mutates via addWeek/addDays etc.
        // Wall-clock values are what matter; later we re-parse them as
        // therapist-local strings via parseUserLocalToUtc().
        $currentStart = Carbon::parse($localStart->toDateTimeString());
        $currentEnd = Carbon::parse($localEnd->toDateTimeString());

        // First occurrence is the parent; start generating from next interval
        $currentStart = $this->nextRecurrenceDate($currentStart, $recurrenceType);
        $currentEnd = $this->nextRecurrenceDate($currentEnd, $recurrenceType);

        while ($currentStart->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            // Convert current Local occurrence to UTC for storage/validation
            $occurrenceUtcStart = $this->timezoneService->parseUserLocalToUtc($currentStart->toDateTimeString(), $therapist);
            $occurrenceUtcEnd = $this->timezoneService->parseUserLocalToUtc($currentEnd->toDateTimeString(), $therapist);

            $overlapCheck = new OverlapCheckDTO(
                $occurrenceUtcStart->toDateString(),
                $occurrenceUtcStart->toTimeString(),
                $occurrenceUtcEnd->toTimeString(),
            );
            $noExclusions = OverlapExclusionsDTO::none();

            $this->validateOverlap($therapist, $overlapCheck, $noExclusions);
            foreach ($students as $student) {
                $this->validateOverlap($student, $overlapCheck, $noExclusions);
            }

            $groupBatchNumber = $isGroup
                ? $this->repository->generateBatchNumber('group')
                : null;

            foreach ($studentIds as $studentId) {
                $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId)
                    ?? throw new \InvalidArgumentException("Student {$studentId} has no school assigned.");

                $occurrences->push($this->repository->create([
                    'therapist_id' => $parentSchedule->therapist_id,
                    'student_id' => $studentId,
                    'ssa_id' => $parentSchedule->ssa_id,
                    'service_id' => $parentSchedule->service_id,
                    'school_id' => $schoolId,
                    'parent_schedule_id' => $parentSchedule->id,
                    'schedule_date' => $occurrenceUtcStart->toDateString(),
                    'start_time' => $occurrenceUtcStart->toTimeString(),
                    'end_time' => $occurrenceUtcEnd->toTimeString(),
                    'recurrence_type' => $parentSchedule->recurrence_type,
                    'recurrence_end_date' => $endDate->toDateString(),
                    'is_group' => $isGroup,
                    'recurring_batch_number' => $parentSchedule->recurring_batch_number,
                    'group_batch_number' => $groupBatchNumber,
                    'status' => ScheduleStatus::SCHEDULED,
                    'billing_status' => BillingStatus::PENDING,
                    'is_billable' => $this->isSchoolBillable($schoolId),
                    'notes' => $parentSchedule->notes,
                    'location_details' => $parentSchedule->location_details,
                    'created_by' => $parentSchedule->created_by,
                    'updated_by' => $parentSchedule->updated_by,
                ]));
            }

            $currentStart = $this->nextRecurrenceDate($currentStart, $recurrenceType);
            $currentEnd = $this->nextRecurrenceDate($currentEnd, $recurrenceType);
        }

        return $occurrences;
    }

    /**
     * Create occurrences from provided dates
     *
     * @param  array<string>  $occurrenceDates  Local dates as Y-m-d strings
     * @param  string  $parentScheduleDate  Local date as Y-m-d string (to filter out from occurrences)
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Schedule>
     */
    private function createOccurrencesFromDates(Schedule $parentSchedule, array $occurrenceDates, string $parentScheduleDate, array $studentIds, bool $isGroup, User $therapist): Collection
    {
        $students = $this->userRepository->findByIds($studentIds);
        $occurrences = collect();

        // Parent stores UTC. Convert to therapist-local so we can combine with
        // user-local occurrence dates and reconvert to UTC per occurrence.
        $parentUtcStart = $parentSchedule->startUtc();
        $parentUtcEnd = $parentSchedule->endUtc();
        $durationMinutes = (int) $parentUtcStart->diffInMinutes($parentUtcEnd);

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $parentLocalStart = $parentUtcStart->setTimezone($tz);
        $localStartTime = $parentLocalStart->format('H:i');

        // Filter out dates that match the parent schedule date (already created as parent schedule)
        $occurrenceDates = array_filter($occurrenceDates, function ($dateStr) use ($parentScheduleDate) {
            return $dateStr !== $parentScheduleDate;
        });

        foreach ($occurrenceDates as $occurrenceDateStr) {
            // Ensure occurrence date is just a date string (Y-m-d)
            $cleanOccurrenceDate = $occurrenceDateStr;
            if (str_contains($occurrenceDateStr, ' ')) {
                $cleanOccurrenceDate = explode(' ', $occurrenceDateStr)[0];
            }

            // Combine the user-local occurrence date with the user-local start
            // time, then convert to UTC for storage.
            $localDateTimeStr = $cleanOccurrenceDate.' '.$localStartTime;
            $occurrenceUtcStart = $this->timezoneService->parseUserLocalToUtc($localDateTimeStr, $therapist);
            $occurrenceUtcEnd = $occurrenceUtcStart->copy()->addMinutes($durationMinutes);

            // Validate for weekends unless the school allows weekend scheduling.
            $localDate = Carbon::parse($cleanOccurrenceDate);
            if ($localDate->isWeekend() && ! $this->schoolAllowsWeekendScheduling($parentSchedule)) {
                throw new \InvalidArgumentException(sprintf('Occurrence date %s falls on a weekend and cannot be scheduled.', $cleanOccurrenceDate));
            }

            $overlapCheck = new OverlapCheckDTO(
                $occurrenceUtcStart->toDateString(),
                $occurrenceUtcStart->toTimeString(),
                $occurrenceUtcEnd->toTimeString(),
            );
            $noExclusions = OverlapExclusionsDTO::none();

            $this->validateOverlap($therapist, $overlapCheck, $noExclusions);
            foreach ($students as $student) {
                $this->validateOverlap($student, $overlapCheck, $noExclusions);
            }

            $groupBatchNumber = $isGroup
                ? $this->repository->generateBatchNumber('group')
                : null;

            foreach ($studentIds as $studentId) {
                $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId)
                    ?? throw new \InvalidArgumentException("Student {$studentId} has no school assigned.");

                $occurrences->push($this->repository->create([
                    'therapist_id' => $parentSchedule->therapist_id,
                    'student_id' => $studentId,
                    'ssa_id' => $parentSchedule->ssa_id,
                    'service_id' => $parentSchedule->service_id,
                    'school_id' => $schoolId,
                    'parent_schedule_id' => $parentSchedule->id,
                    'schedule_date' => $occurrenceUtcStart->toDateString(),
                    'start_time' => $occurrenceUtcStart->toTimeString(),
                    'end_time' => $occurrenceUtcEnd->toTimeString(),
                    'recurrence_type' => $parentSchedule->recurrence_type,
                    'recurrence_end_date' => $parentSchedule->recurrence_end_date?->format('Y-m-d'),
                    'is_group' => $isGroup,
                    'recurring_batch_number' => $parentSchedule->recurring_batch_number,
                    'group_batch_number' => $groupBatchNumber,
                    'status' => ScheduleStatus::SCHEDULED,
                    'billing_status' => BillingStatus::PENDING,
                    'is_billable' => $this->isSchoolBillable($schoolId),
                    'notes' => $parentSchedule->notes,
                    'location_details' => $parentSchedule->location_details,
                    'created_by' => $parentSchedule->created_by,
                    'updated_by' => $parentSchedule->updated_by,
                ]));
            }
        }

        return $occurrences;
    }

    /**
     * Compare the submitted occurrence dates against the local dates already
     * stored for this schedule's batch (anchor + unbilled future siblings).
     * Returns true when the sets differ — i.e. a date was added or removed.
     *
     * @param  array<int, string>  $submittedDates  user-local Y-m-d strings (may include trailing time)
     */
    private function occurrenceListDiffersFromBatch(Schedule $schedule, array $submittedDates): bool
    {
        if ($schedule->recurring_batch_number === null) {
            return true;
        }

        /** @var User|null $therapist */
        $therapist = $this->userRepository->findById($schedule->therapist_id);
        if ($therapist === null) {
            return true;
        }

        $tz = $this->timezoneService->resolveTimezone($therapist);

        $existingDates = $this->repository
            ->getUnbilledFutureRecurringOccurrencesByBatch(
                $schedule->recurring_batch_number,
                $schedule->schedule_date->format('Y-m-d'),
            )
            ->map(fn (Schedule $s): string => $s->startUtc()->setTimezone($tz)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $normalizedSubmitted = collect($submittedDates)
            ->map(static fn (string $date): string => str_contains($date, ' ') ? explode(' ', $date)[0] : $date)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $existingDates !== $normalizedSubmitted;
    }

    private function calculateRecurrenceEndDate(string $startDate, RecurrenceType $type, int $occurrenceCount): string
    {
        $cursor = Carbon::parse($startDate);

        for ($i = 1; $i < $occurrenceCount; $i += 1) {
            $cursor = $this->nextRecurrenceDate($cursor, $type);
        }

        return $cursor->toDateString();
    }

    private function nextRecurrenceDate(CarbonInterface $date, RecurrenceType $type): CarbonInterface
    {
        return match ($type) {
            RecurrenceType::DAILY => $date->copy()->addDay(),
            RecurrenceType::WEEKLY => $date->copy()->addWeek(),
            RecurrenceType::BI_WEEKLY => $date->copy()->addWeeks(2),
            RecurrenceType::MONTHLY => $date->copy()->addMonth(),
            RecurrenceType::NONE, RecurrenceType::CUSTOM_WEEKLY => $date->copy(),
        };
    }

    /**
     * Editable occurrence rows for a recurring schedule's edit form: the anchor
     * plus its unbilled future siblings, each as a user-local date + start/end
     * time, with a flag marking rows whose time differs from the series default.
     *
     * @return array<int, array{date: string, start_time: string, end_time: string, is_custom_time: bool, is_anchor: bool}>
     */
    public function buildOccurrenceRows(Schedule $schedule, User $therapist): array
    {
        if (! $schedule->isRecurring() || $schedule->recurring_batch_number === null) {
            return [];
        }

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $seriesStartTime = $schedule->localStart($tz)->format('H:i');
        $seriesEndTime = $schedule->localEnd($tz)->format('H:i');

        return $this->repository
            ->getUnbilledFutureRecurringOccurrencesByBatch(
                $schedule->recurring_batch_number,
                $schedule->schedule_date->format('Y-m-d'),
            )
            ->map(function (Schedule $occurrence) use ($tz, $seriesStartTime, $seriesEndTime, $schedule): array {
                $start = $occurrence->localStart($tz);
                $end = $occurrence->localEnd($tz);
                $startTime = $start->format('H:i');
                $endTime = $end->format('H:i');

                return [
                    'date' => $start->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_custom_time' => $startTime !== $seriesStartTime || $endTime !== $seriesEndTime,
                    'is_anchor' => $occurrence->id === $schedule->id,
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    public function generateBatchNumber(string $type = 'recurring'): string
    {
        return $this->repository->generateBatchNumber($type);
    }

    public function updateBillingStatus(User $therapist, int $scheduleId, BillingStatus $status): Schedule
    {
        return DB::transaction(function () use ($therapist, $scheduleId, $status): Schedule {
            $schedule = $this->repository->findForTherapist($therapist, $scheduleId);

            if (! $schedule) {
                throw new \RuntimeException('Schedule not found for therapist.');
            }

            return $this->repository->updateBillingStatus($schedule, $status);
        });
    }

    /**
     * @param  array<int, int>  $scheduleIds
     */
    public function bulkUpdateBillingStatus(User $therapist, array $scheduleIds, BillingStatus $status): int
    {
        return DB::transaction(function () use ($therapist, $scheduleIds, $status): int {
            if ($scheduleIds === []) {
                return 0;
            }

            // Ensure all schedules belong to therapist
            $schedules = Schedule::query()
                ->forTherapist($therapist)
                ->whereIn('id', $scheduleIds)
                ->pluck('id')
                ->all();

            if ($schedules === []) {
                return 0;
            }

            return $this->repository->bulkUpdateBillingStatus($schedules, $status);
        });
    }

    private function validateOverlap(User $user, OverlapCheckDTO $check, OverlapExclusionsDTO $exclusions): void
    {
        if (! $this->repository->hasOverlap($user, $check, $exclusions)) {
            return;
        }

        $message = $user->isTherapist()
            ? 'You already have another schedule at this time. Please choose a different time.'
            : 'The student already has another schedule at this time. Please choose a different time.';

        throw new ScheduleOverlapException($message);
    }
}
