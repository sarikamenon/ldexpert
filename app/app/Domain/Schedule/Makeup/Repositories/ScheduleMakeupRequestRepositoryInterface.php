<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Repositories;

use App\DTOs\Schedule\Makeup\CreateMakeupRequestDTO;
use App\DTOs\Schedule\Makeup\RecordMakeupResponseDTO;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface ScheduleMakeupRequestRepositoryInterface
{
    /**
     * Schedule IDs that ALREADY have a make-up request for this closure event,
     * used by the generator to skip duplicates.
     *
     * @return Collection<int, int>
     */
    public function existingScheduleIdsForEvent(SchoolCalendarEvent $event): Collection;

    /**
     * Map of "studentId:therapistId" => [batch_number, response_token] for any
     * existing make-up request rows belonging to this event. The generator
     * uses this to keep the same batch identifiers across all rows that belong
     * in one parent email, even across re-runs and across days of a multi-day
     * closure.
     *
     * @return Collection<string, array{batch_number: string, response_token: string}>
     */
    public function batchIdentifiersForEvent(SchoolCalendarEvent $event): Collection;

    /**
     * Calendar events whose [start_date, end_date] overlaps [$from, $to].
     * Used by the generator to scope its scan to the lookahead window.
     *
     * @return Collection<int, SchoolCalendarEvent>
     */
    public function listEventsOverlappingWindow(CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * Scheduled (status = SCHEDULED) sessions for the event's school on the
     * given date that do NOT already have a make-up request row.
     *
     * @param  Collection<int, int>  $excludeScheduleIds  ids to skip (already-handled this run)
     * @return Collection<int, Schedule>
     */
    public function inScopeSchedulesForEventOnDate(
        SchoolCalendarEvent $event,
        CarbonInterface $date,
        Collection $excludeScheduleIds,
    ): Collection;

    public function create(CreateMakeupRequestDTO $dto): ScheduleMakeupRequest;

    /**
     * Return every make-up request row in the batch the given response token
     * belongs to. The endpoint resolves token → batch_number → all rows
     * sharing that batch_number. Empty collection if the token is unknown.
     *
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function findBatchByResponseToken(string $token): Collection;

    public function findAndLock(int $id): ScheduleMakeupRequest;

    /**
     * Pending rows whose reminder_date <= $on. Used by SendDueRemindersJob.
     *
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function listDueForReminder(CarbonInterface $on): Collection;

    /**
     * Pending rows due for reminder ($on), grouped by `batch_number`. One key
     * per batch — every row in the batch belongs in a single parent email.
     *
     * Eager-loads everything the mailable + sender need:
     *  - `schedule.ssa` (frequency_type → template variant)
     *  - `schedule.service`
     *  - `student.studentProfile` (parent email + name)
     *  - `therapist.therapistProfile` (from address fields)
     *  - `calendarEvent` (event subject / closure context)
     *
     * @return Collection<string, EloquentCollection<int, ScheduleMakeupRequest>>
     */
    public function listPendingDueBatches(CarbonInterface $on): Collection;

    /**
     * Flip every row in the batch to `sent` and stamp `reminder_sent_at`.
     * Returns the number of rows updated.
     */
    public function markBatchSent(string $batchNumber, CarbonInterface $sentAt): int;

    /**
     * Flip every row in the batch to `failed`. Returns the number of rows updated.
     */
    public function markBatchFailed(string $batchNumber): int;

    /**
     * Sent rows whose deadline_date < $on with no response recorded.
     * Used by AutoDeclineOverdueRemindersJob.
     *
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function listOverdueForResponse(CarbonInterface $on): Collection;

    /**
     * Pending rows for a calendar event that no longer applies — used by the
     * observer to soft-delete orphans on closure delete/edit.
     *
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function listPendingForEvent(SchoolCalendarEvent $event): Collection;

    public function countForTherapist(User $therapist, ?ScheduleMakeupRequestStatus $status = null): int;

    /**
     * Paged list for the therapist's "Make-Up Requests" page.
     *
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function pageForTherapist(
        User $therapist,
        ?ScheduleMakeupRequestStatus $status,
        int $offset,
        int $limit,
    ): Collection;

    /**
     * Apply a response (parent click or therapist manual) to a single row.
     * Caller is expected to have already locked the row via findAndLock().
     */
    public function recordResponse(ScheduleMakeupRequest $request, RecordMakeupResponseDTO $dto): ScheduleMakeupRequest;

    /**
     * Mark all overdue `sent` rows as system-declined in one statement.
     * Returns the number of rows updated.
     */
    public function bulkAutoDecline(CarbonInterface $on): int;

    /**
     * Link a freshly-booked make-up session to the originating request and
     * flip the request to SCHEDULED. Returns the updated row.
     */
    public function linkBookedSchedule(ScheduleMakeupRequest $request, int $scheduleId): ScheduleMakeupRequest;
}
