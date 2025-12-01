<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
    ) {}

    public function getSchedules(User $therapist, ScheduleFilterDTO $filters): Collection
    {
        return $this->repository->getSchedulesForTherapist($therapist, $filters);
    }

    public function getPendingCount(User $therapist): int
    {
        return $this->repository->getPendingCount($therapist);
    }

    public function getSchools(User $therapist): Collection
    {
        return $this->repository->getSchoolsForTherapist($therapist);
    }

    public function getStudents(User $therapist): Collection
    {
        return $this->repository->getStudentsForTherapist($therapist);
    }

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

            $service = Service::query()->findOrFail($dto->serviceId);
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
                    $schoolId = StudentProfile::query()
                        ->where('user_id', $studentId)
                        ->value('school_id');

                    $data = [
                        'therapist_id' => $therapist->id,
                        'student_id' => $studentId,
                        'ssa_id' => $dto->ssaId,
                        'service_id' => $dto->serviceId,
                        'school_id' => $schoolId,
                        'parent_schedule_id' => null,
                        'schedule_date' => $dto->scheduleDate,
                        'start_time' => $dto->startTime,
                        'end_time' => $dto->endTime,
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
                $firstSchoolId = StudentProfile::query()
                    ->where('user_id', $firstStudentId)
                    ->value('school_id');

                /** @var Schedule $parentSchedule */
                $parentSchedule = $this->repository->create([
                    'therapist_id' => $therapist->id,
                    'student_id' => $firstStudentId,
                    'ssa_id' => $dto->ssaId,
                    'service_id' => $dto->serviceId,
                    'school_id' => $firstSchoolId,
                    'parent_schedule_id' => null,
                    'schedule_date' => $dto->scheduleDate,
                    'start_time' => $dto->startTime,
                    'end_time' => $dto->endTime,
                    'recurrence_type' => $dto->recurrenceType,
                    'recurrence_end_date' => $recurrenceEndDate,
                    'is_group' => $isGroup,
                    'recurring_batch_number' => $recurringBatchNumber,
                    'group_batch_number' => $isGroup ? $this->repository->generateBatchNumber('group') : null,
                    'status' => ScheduleStatus::SCHEDULED,
                    'billing_status' => BillingStatus::PENDING,
                    'notes' => $dto->notes,
                    'location_details' => $dto->locationDetails,
                ]);

                $schedules->push($parentSchedule);

                $occurrences = $this->generateRecurringOccurrences($parentSchedule, $dto->studentIds, $isGroup);
                $schedules = $schedules->merge($occurrences);
            }

            /** @var Schedule $first */
            $first = $schedules->first();

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

            // If this is the parent of a recurring series, delete all in the batch
            if (! $schedule->isOccurrence() && $schedule->isRecurring() && $schedule->recurring_batch_number) {
                $this->repository->getRecurringOccurrencesByBatch($schedule->recurring_batch_number)
                    ->each(fn(Schedule $occurrence) => $this->repository->delete($occurrence));
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
     * @param array<int, int> $studentIds
     * @return Collection<int, Schedule>
     */
    public function generateRecurringOccurrences(Schedule $parentSchedule, array $studentIds, bool $isGroup): Collection
    {
        if ($parentSchedule->recurrence_type === RecurrenceType::NONE || ! $parentSchedule->recurrence_end_date) {
            return collect([]);
        }

        $occurrences = collect();
        $startDate = $parentSchedule->schedule_date;
        $endDate = $parentSchedule->recurrence_end_date;

        $current = $startDate->copy();

        // First occurrence is the parent; start generating from next interval
        $current = $this->nextRecurrenceDate($current, $parentSchedule->recurrence_type);

        while ($current->lte($endDate)) {
            $groupBatchNumber = $isGroup
                ? $this->repository->generateBatchNumber('group')
                : null;

            foreach ($studentIds as $studentId) {
                $schoolId = StudentProfile::query()
                    ->where('user_id', $studentId)
                    ->value('school_id');

                $occurrences->push($this->repository->create([
                    'therapist_id' => $parentSchedule->therapist_id,
                    'student_id' => $studentId,
                    'ssa_id' => $parentSchedule->ssa_id,
                    'service_id' => $parentSchedule->service_id,
                    'school_id' => $schoolId,
                    'parent_schedule_id' => $parentSchedule->id,
                    'schedule_date' => $current->toDateString(),
                    'start_time' => $parentSchedule->start_time,
                    'end_time' => $parentSchedule->end_time,
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

            $current = $this->nextRecurrenceDate($current, $parentSchedule->recurrence_type);
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
}
