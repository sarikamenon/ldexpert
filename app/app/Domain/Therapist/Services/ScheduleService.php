<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\DataTablesParamsDTO;
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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly UserTimezoneService $timezoneService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
        private readonly StudentRepositoryInterface $studentRepository,
    ) {}

    /** @return Collection<int, Schedule> */
    public function getSchedules(User $therapist, ScheduleFilterDTO $filters): Collection
    {
        return $this->repository->getSchedulesForTherapist($therapist, $filters);
    }

    public function findForTherapist(User $therapist, int $scheduleId): ?Schedule
    {
        return $this->repository->findForTherapist($therapist, $scheduleId);
    }

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

    /** @return Collection<int, Schedule> */
    public function getPendingSchedules(User $therapist, ?ScheduleFilterDTO $filters = null): Collection
    {
        return $this->repository->getPendingSchedules($therapist, $filters);
    }

    /** @return LengthAwarePaginator<Schedule> */
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

    /** @return Collection<int, \App\Models\ServiceSupportAgreement> */
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

            if (! $this->repository->validateStudentsShareService($therapist, $dto->studentIds, $dto->serviceId)) {
                throw new \InvalidArgumentException('Selected students do not share this service via an active SSA.');
            }

            // Timezone Conversion & Overlap Check
            $localStartStr = $dto->scheduleDate.' '.$dto->startTime;
            $utcStart = $this->timezoneService->parseUserLocalToUtc($localStartStr, $therapist);
            $utcEnd = $utcStart->copy()->addMinutes($dto->durationMinutes);

            // Fetch students and service before overlap checks (tests expect these to be called)
            $students = $this->userRepository->findByIds($dto->studentIds);
            $service = $this->serviceRepository->findOrFail($dto->serviceId);

            // Validate Therapist Overlap
            $this->validateOverlap($therapist, $utcStart->toDateString(), $utcStart->toTimeString(), $utcEnd->toTimeString(), null, true);

            // Validate Student Overlap
            foreach ($students as $student) {
                $this->validateOverlap($student, $utcStart->toDateString(), $utcStart->toTimeString(), $utcEnd->toTimeString(), null, false);
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
                    $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId);

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
                        'notes' => $dto->notes,
                        'location_details' => $dto->locationDetails,
                    ];

                    $schedules->push($this->repository->create($data));
                }
            } else {
                // Recurring schedule: create parent + occurrences
                // Parent schedule (per first student, used to store rules)
                $firstStudentId = $dto->studentIds[0];
                $firstSchoolId = $this->studentRepository->getSchoolIdByUserId($firstStudentId);

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

            // Validate Therapist Overlap (exclude current schedule)
            $this->validateOverlap($therapist, $utcStart->toDateString(), $utcStart->toTimeString(), $utcEnd->toTimeString(), $scheduleId, true);

            // Validate Student Overlap (need to know which students are involved)
            // Use existing student_id for single, or if group?
            // UpdateScheduleDTO doesn't have studentIds, it updates a single schedule.
            // If it's a group schedule, updates might affect others if we propagated changes, but here we update ONE schedule unless logic differs.
            // However, `updateSchedule` in this service seems to update one schedule record.
            // If it's a group, typically we update all in the batch?
            // The existing code:
            /*
            $updated = $this->repository->update($schedule, $data);
            // Regenerate occurrences for recurring schedules...
            */
            // If it updates one schedule, we check overlap for that schedule's student.
            $student = $this->userRepository->findById($schedule->student_id);
            if ($student) {
                $this->validateOverlap($student, $utcStart->toDateString(), $utcStart->toTimeString(), $utcEnd->toTimeString(), $scheduleId, false);
            }

            // Update data with UTC values
            $data['schedule_date'] = $utcStart->toDateString();
            $data['start_time'] = $utcStart->toTimeString();
            $data['end_time'] = $utcEnd->toTimeString();

            // If recurrence type or end date changed, regenerate occurrences
            $recurrenceTypeChanged = array_key_exists('recurrence_type', $data)
                && $schedule->recurrence_type?->value !== $data['recurrence_type'];
            $recurrenceEndChanged = array_key_exists('recurrence_end_date', $data)
                && $schedule->recurrence_end_date?->format('Y-m-d') !== $data['recurrence_end_date'];

            if ($recurrenceTypeChanged || $recurrenceEndChanged) {
                // Remove existing occurrences (but keep current schedule as parent)
                if ($schedule->recurring_batch_number) {
                    $this->repository->getRecurringOccurrencesByBatch($schedule->recurring_batch_number)
                        ->each(function (Schedule $occurrence) use ($schedule): void {
                            if ($occurrence->id !== $schedule->id) {
                                $this->repository->delete($occurrence);
                            }
                        });
                }

                if (! isset($data['recurrence_type'])) {
                    $data['recurrence_type'] = RecurrenceType::NONE->value;
                }

                if ($data['recurrence_type'] !== RecurrenceType::NONE->value) {
                    $data['recurring_batch_number'] = $this->repository->generateBatchNumber('recurring');
                } else {
                    $data['recurring_batch_number'] = null;
                    $data['recurrence_end_date'] = null;
                }
            }

            $updated = $this->repository->update($schedule, $data);

            // Regenerate occurrences for recurring schedules
            if ($updated->isRecurring()) {
                $studentIds = [$updated->student_id];
                if ($updated->isGroup()) {
                    $groupSchedules = $this->repository
                        ->getGroupSchedulesByBatch($updated->group_batch_number ?? '')
                        ->pluck('student_id')
                        ->unique()
                        ->all();

                    if ($groupSchedules !== []) {
                        $studentIds = $groupSchedules;
                    }
                }

                $this->generateRecurringOccurrences($updated, $studentIds, $updated->isGroup());
            }

            // Update billing status if provided
            if ($dto->billingStatus instanceof BillingStatus) {
                $updated = $this->repository->updateBillingStatus($updated, $dto->billingStatus);
            }

            ScheduleUpdated::dispatch($updated);

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

            // If this is the parent of a recurring series, delete all in the batch
            if (! $schedule->isOccurrence() && $schedule->isRecurring() && $schedule->recurring_batch_number) {
                $this->repository->getRecurringOccurrencesByBatch($schedule->recurring_batch_number)
                    ->each(fn (Schedule $occurrence) => $this->repository->delete($occurrence));
            }

            $this->repository->delete($schedule);
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
        if ($parentSchedule->recurrence_type === RecurrenceType::NONE || ! $parentSchedule->recurrence_end_date) {
            return collect([]);
        }

        $therapist = $this->userRepository->findById($parentSchedule->therapist_id);
        $students = $this->userRepository->findByIds($studentIds);

        $occurrences = collect();

        // Parent stores UTC. Convert to Local for calculation.
        // Assuming end date is end of the recurrence period (date only)
        $endDate = $parentSchedule->recurrence_end_date;

        $scheduleDate = $parentSchedule->schedule_date->format('Y-m-d');
        $startTime = $parentSchedule->start_time->format('H:i:s');
        $endTime = $parentSchedule->end_time->format('H:i:s');

        $utcStart = Carbon::parse($scheduleDate.' '.$startTime);
        $utcEnd = Carbon::parse($scheduleDate.' '.$endTime);
        if ($utcEnd->lt($utcStart)) {
            $utcEnd->addDay();
        }

        $localStart = $this->timezoneService->toUserTimezone($utcStart, $therapist);
        $localEnd = $this->timezoneService->toUserTimezone($utcEnd, $therapist);

        $currentStart = $localStart->copy();
        $currentEnd = $localEnd->copy();

        // First occurrence is the parent; start generating from next interval
        $currentStart = $this->nextRecurrenceDate($currentStart, $parentSchedule->recurrence_type);
        $currentEnd = $this->nextRecurrenceDate($currentEnd, $parentSchedule->recurrence_type);

        while ($currentStart->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            // Convert current Local occurrence to UTC for storage/validation
            $occurrenceUtcStart = $this->timezoneService->parseUserLocalToUtc($currentStart->toDateTimeString(), $therapist);
            $occurrenceUtcEnd = $this->timezoneService->parseUserLocalToUtc($currentEnd->toDateTimeString(), $therapist);

            // Check Overlap
            $this->validateOverlap($therapist, $occurrenceUtcStart->toDateString(), $occurrenceUtcStart->toTimeString(), $occurrenceUtcEnd->toTimeString(), null, true);
            foreach ($students as $student) {
                $this->validateOverlap($student, $occurrenceUtcStart->toDateString(), $occurrenceUtcStart->toTimeString(), $occurrenceUtcEnd->toTimeString(), null, false);
            }

            $groupBatchNumber = $isGroup
                ? $this->repository->generateBatchNumber('group')
                : null;

            foreach ($studentIds as $studentId) {
                $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId);

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
                    'notes' => $parentSchedule->notes,
                    'location_details' => $parentSchedule->location_details,
                ]));
            }

            $currentStart = $this->nextRecurrenceDate($currentStart, $parentSchedule->recurrence_type);
            $currentEnd = $this->nextRecurrenceDate($currentEnd, $parentSchedule->recurrence_type);
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

        // Get time from parent schedule (stored as UTC)
        $startTime = $parentSchedule->start_time->format('H:i');
        $endTime = $parentSchedule->end_time->format('H:i');

        // Format schedule date to ensure it's just a date string
        $parentScheduleDateStr = $parentSchedule->schedule_date->format('Y-m-d');

        // Parse parent schedule date/time to get duration
        $parentUtcStart = Carbon::parse($parentScheduleDateStr.' '.$startTime);
        $parentUtcEnd = Carbon::parse($parentScheduleDateStr.' '.$endTime);
        if ($parentUtcEnd->lt($parentUtcStart)) {
            $parentUtcEnd->addDay();
        }
        $durationMinutes = (int) $parentUtcStart->diffInMinutes($parentUtcEnd);

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

            // Ensure start time is in H:i format
            $cleanStartTime = $startTime;
            if (str_contains($startTime, ':')) {
                $parts = explode(':', $startTime);
                $cleanStartTime = $parts[0].':'.$parts[1]; // Take only H:i
            }

            // Parse local date string and combine with start time
            $localDateTimeStr = $cleanOccurrenceDate.' '.$cleanStartTime;

            // Convert to UTC for storage/validation
            $occurrenceUtcStart = $this->timezoneService->parseUserLocalToUtc($localDateTimeStr, $therapist);
            $occurrenceUtcEnd = $occurrenceUtcStart->copy()->addMinutes($durationMinutes);

            // Validate for weekends (should already be validated in request, but double-check)
            $localDate = Carbon::parse($cleanOccurrenceDate);
            if ($localDate->isWeekend()) {
                throw new \InvalidArgumentException(sprintf('Occurrence date %s falls on a weekend and cannot be scheduled.', $cleanOccurrenceDate));
            }

            // Check Overlap
            $this->validateOverlap($therapist, $occurrenceUtcStart->toDateString(), $occurrenceUtcStart->toTimeString(), $occurrenceUtcEnd->toTimeString(), null, true);
            foreach ($students as $student) {
                $this->validateOverlap($student, $occurrenceUtcStart->toDateString(), $occurrenceUtcStart->toTimeString(), $occurrenceUtcEnd->toTimeString(), null, false);
            }

            $groupBatchNumber = $isGroup
                ? $this->repository->generateBatchNumber('group')
                : null;

            foreach ($studentIds as $studentId) {
                $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId);

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

    private function nextRecurrenceDate(Carbon $date, RecurrenceType $type): Carbon
    {
        return match ($type) {
            RecurrenceType::DAILY => $date->copy()->addDay(),
            RecurrenceType::WEEKLY => $date->copy()->addWeek(),
            RecurrenceType::BI_WEEKLY => $date->copy()->addWeeks(2),
            RecurrenceType::MONTHLY => $date->copy()->addMonth(),
            RecurrenceType::NONE => $date->copy(),
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

    private function validateOverlap(User $user, string $date, string $startTime, string $endTime, ?int $excludeScheduleId = null, bool $isTherapist = false): void
    {
        if ($this->repository->hasOverlap($user, $date, $startTime, $endTime, $excludeScheduleId)) {
            $message = $isTherapist
                ? 'You already have another schedule at this time. Please choose a different time.'
                : sprintf('The student already has another schedule at this time. Please choose a different time.', $user->name ?? 'Student');

            throw new ScheduleOverlapException($message);
        }
    }
}
