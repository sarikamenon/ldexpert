<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\OverlapCheckDTO;
use App\DTOs\OverlapExclusionsDTO;
use App\DTOs\Schedule\OccurrenceInputDTO;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\ScheduleOverlapException;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reconciles the per-occurrence list submitted from the recurring-series editor
 * against the occurrences already stored for a batch.
 *
 * The anchor row (the schedule being edited) is updated by the caller; this
 * service handles only the sibling occurrences. For each submitted row it:
 *   - reuses the matching existing row (same UTC date) and updates time in place,
 *     preserving the row id and any linked session log;
 *   - deletes existing occurrences the user removed from the list;
 *   - creates new occurrences for added dates.
 *
 * Occurrences always stay in the series: a date/time that differs from the
 * series default is a modified exception (iCalendar model), never detached.
 * Billed occurrences are never modified or deleted.
 */
final class OccurrenceSyncService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly UserTimezoneService $timezoneService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly StudentRepositoryInterface $studentRepository,
    ) {}

    /**
     * @param  array<int, OccurrenceInputDTO>  $occurrences  user-local date/time inputs (excludes the anchor)
     * @param  array<int, int>  $studentIds
     */
    public function sync(
        Schedule $anchor,
        array $occurrences,
        array $studentIds,
        bool $isGroup,
        User $therapist,
    ): void {
        $batchNumber = $anchor->recurring_batch_number;
        if ($batchNumber === null) {
            return;
        }

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $seriesStartTime = $anchor->startUtc()->setTimezone($tz)->format('H:i');
        $seriesEndTime = $anchor->endUtc()->setTimezone($tz)->format('H:i');
        $anchorLocalDate = $anchor->startUtc()->setTimezone($tz)->format('Y-m-d');

        // Existing unbilled future siblings, keyed by their UTC schedule_date so we
        // can match a submitted row to the row it should update in place.
        $existing = $this->repository
            ->getUnbilledFutureRecurringOccurrencesByBatch($batchNumber, $anchor->schedule_date->format('Y-m-d'))
            ->reject(fn (Schedule $s): bool => $s->id === $anchor->id)
            ->keyBy(fn (Schedule $s): string => $s->schedule_date->format('Y-m-d'));

        $students = $this->userRepository->findByIds($studentIds);
        $matchedUtcDates = [];

        foreach ($occurrences as $occurrence) {
            if ($occurrence->date === $anchorLocalDate) {
                continue;
            }

            $startTime = $occurrence->startTime ?? $seriesStartTime;
            $endTime = $occurrence->endTime ?? $seriesEndTime;

            $utcStart = $this->timezoneService->parseUserLocalToUtc($occurrence->date.' '.$startTime, $therapist);
            $utcEnd = $this->resolveUtcEnd($occurrence->date, $startTime, $endTime, $therapist);

            $utcDate = $utcStart->toDateString();
            $matchedUtcDates[] = $utcDate;

            $match = $existing->get($utcDate);

            if ($match instanceof Schedule) {
                $this->updateExisting($match, $anchor, $utcStart->toTimeString(), $utcEnd->toTimeString(), $therapist, $students);

                continue;
            }

            // A submitted date with no existing match is a new occurrence (an
            // end-date extension or a moved date). Following the iCalendar model,
            // it stays in the series — a per-occurrence date/time that differs
            // from the series default is a modified exception, not a detached row.
            $this->createOccurrence($anchor, $utcStart->toDateString(), $utcStart->toTimeString(), $utcEnd->toTimeString(), $studentIds, $isGroup, $therapist, $students);
        }

        // Anything left in $existing that the user did not resubmit was removed.
        $existing
            ->reject(fn (Schedule $s, string $utcDate): bool => in_array($utcDate, $matchedUtcDates, true))
            ->each(fn (Schedule $s) => $this->repository->delete($s));
    }

    /**
     * Update an existing occurrence's time in place, keeping it in the series.
     * A time that differs from the series default is a modified exception.
     *
     * @param  Collection<int, User>  $students
     */
    private function updateExisting(
        Schedule $occurrence,
        Schedule $anchor,
        string $utcStartTime,
        string $utcEndTime,
        User $therapist,
        Collection $students,
    ): void {
        $timeChanged = $occurrence->start_time->format('H:i:s') !== $utcStartTime
            || $occurrence->end_time->format('H:i:s') !== $utcEndTime;

        if ($timeChanged) {
            $this->assertNoOverlap(
                $therapist,
                $students,
                $occurrence->schedule_date->format('Y-m-d'),
                $utcStartTime,
                $utcEndTime,
                $occurrence->id,
            );
        }

        // The occurrence stays in the series even if its time now differs from
        // the series default (iCalendar exception). Keep its end date in sync
        // with the anchor.
        $this->repository->update($occurrence, [
            'start_time' => $utcStartTime,
            'end_time' => $utcEndTime,
            'recurrence_end_date' => $anchor->recurrence_end_date?->format('Y-m-d'),
        ]);
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  Collection<int, User>  $students
     */
    private function createOccurrence(
        Schedule $anchor,
        string $utcDate,
        string $utcStartTime,
        string $utcEndTime,
        array $studentIds,
        bool $isGroup,
        User $therapist,
        Collection $students,
    ): void {
        $this->assertNoOverlap($therapist, $students, $utcDate, $utcStartTime, $utcEndTime, null);

        $groupBatchNumber = $isGroup
            ? $this->repository->generateBatchNumber('group')
            : null;

        foreach ($studentIds as $studentId) {
            $schoolId = $this->studentRepository->getSchoolIdByUserId($studentId)
                ?? throw new \InvalidArgumentException("Student {$studentId} has no school assigned.");

            // New occurrences always join the series — a differing date/time is a
            // modified exception, not a detached standalone schedule.
            $this->repository->create([
                'therapist_id' => $anchor->therapist_id,
                'student_id' => $studentId,
                'ssa_id' => $anchor->ssa_id,
                'service_id' => $anchor->service_id,
                'school_id' => $schoolId,
                'parent_schedule_id' => $anchor->id,
                'schedule_date' => $utcDate,
                'start_time' => $utcStartTime,
                'end_time' => $utcEndTime,
                'recurrence_type' => $anchor->recurrence_type,
                'recurrence_end_date' => $anchor->recurrence_end_date?->format('Y-m-d'),
                'is_group' => $isGroup,
                'recurring_batch_number' => $anchor->recurring_batch_number,
                'group_batch_number' => $groupBatchNumber,
                'status' => ScheduleStatus::SCHEDULED,
                'billing_status' => BillingStatus::PENDING,
                'is_billable' => $anchor->is_billable,
                'notes' => $anchor->notes,
                'location_details' => $anchor->location_details,
                'created_by' => $anchor->created_by,
                'updated_by' => $anchor->updated_by,
            ]);
        }
    }

    private function resolveUtcEnd(string $date, string $startTime, string $endTime, User $therapist): CarbonInterface
    {
        $utcStart = $this->timezoneService->parseUserLocalToUtc($date.' '.$startTime, $therapist);
        $utcEnd = $this->timezoneService->parseUserLocalToUtc($date.' '.$endTime, $therapist);

        // End before start means the session crosses midnight in the user's zone.
        return $utcEnd->lessThanOrEqualTo($utcStart)
            ? $utcEnd->addDay()
            : $utcEnd;
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function assertNoOverlap(
        User $therapist,
        Collection $students,
        string $utcDate,
        string $utcStartTime,
        string $utcEndTime,
        ?int $excludeScheduleId,
    ): void {
        $check = new OverlapCheckDTO($utcDate, $utcStartTime, $utcEndTime);
        $exclusions = $excludeScheduleId === null
            ? OverlapExclusionsDTO::none()
            : new OverlapExclusionsDTO(scheduleId: $excludeScheduleId);

        $this->guard($therapist, $check, $exclusions);
        foreach ($students as $student) {
            $this->guard($student, $check, $exclusions);
        }
    }

    private function guard(User $user, OverlapCheckDTO $check, OverlapExclusionsDTO $exclusions): void
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
