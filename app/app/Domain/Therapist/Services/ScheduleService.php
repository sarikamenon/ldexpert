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
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
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

    public function createSchedule(User $therapist, CreateScheduleDTO $dto): Schedule
    {
        return DB::transaction(function () use ($therapist, $dto): Schedule {
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
                    ScheduleCreated::dispatch($schedule);
                }
            } else {
                // For recurring, only dispatch for the parent/first occurrence to avoid spam
                ScheduleCreated::dispatch($first);
            }

            return $first;
        });
    }

    public function updateSchedule(User $therapist, int $scheduleId, UpdateScheduleDTO $dto): Schedule
    {
        return DB::transaction(function () use ($therapist, $scheduleId, $dto): Schedule {
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
            $occurrenceDatesChanged = $dto->occurrenceDates !== null;
            $recurrenceSettingsChanged = $recurrenceChanged || $endDateChanged || $occurrenceDatesChanged;

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

            // Switching to NONE removes recurring fields and clears parent linkage.
            if ($incomingRecurrenceType === RecurrenceType::NONE) {
                $data['recurrence_type'] = RecurrenceType::NONE->value;
                $data['recurrence_end_date'] = null;
                $data['recurring_batch_number'] = null;
                $data['parent_schedule_id'] = null;
            } elseif ($recurrenceSettingsChanged) {
                // New recurrence settings: this schedule becomes the new series anchor.
                $data['recurrence_type'] = $incomingRecurrenceType->value;
                $data['recurrence_end_date'] = $incomingEndDate;
                $data['recurring_batch_number'] = $this->repository->generateBatchNumber('recurring');
                $data['parent_schedule_id'] = null;
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

            // Update billing status if provided
            if ($dto->billingStatus instanceof BillingStatus) {
                $updated = $this->repository->updateBillingStatus($updated, $dto->billingStatus);
            }

            if ($hasMeaningfulChange) {
                ScheduleUpdated::dispatch($updated);
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

            $this->repository->delete($schedule);
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

            // Validate for weekends (should already be validated in request, but double-check)
            $localDate = Carbon::parse($cleanOccurrenceDate);
            if ($localDate->isWeekend()) {
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
                ]));
            }
        }

        return $occurrences;
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
