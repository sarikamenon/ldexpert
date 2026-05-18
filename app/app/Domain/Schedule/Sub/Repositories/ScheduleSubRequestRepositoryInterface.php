<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\Repositories;

use App\Enums\SubRequestInviteeStatus;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use App\Models\ScheduleSubSsa;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface ScheduleSubRequestRepositoryInterface
{
    /**
     * Returns open requests where the given therapist has an `invited` invitee row.
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listOpenForTherapist(User $sub): Collection;

    /**
     * Count open requests where the given therapist has an `invited` invitee row.
     */
    public function countOpenForTherapist(User $sub): int;

    /**
     * Count open requests raised by this therapist (requester-side dashboard badge).
     */
    public function countMyOpenRequests(User $requester): int;

    /**
     * Returns open + accepted requests raised by this therapist (requester-side list),
     * filtered to schedules whose combined (schedule_date + start_time) UTC instant
     * is at or after $startTimeAtOrAfter (UTC).
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listAsRequester(User $requester, CarbonInterface $startTimeAtOrAfter): Collection;

    public function findOpenForSchedule(int $scheduleId): ?ScheduleSubRequest;

    /**
     * Open requests whose schedule's combined (schedule_date + start_time) UTC instant
     * is on or before $cutoff. Used by the auto-expiry job.
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listOpenOverdue(CarbonInterface $cutoff): Collection;

    /**
     * Return the given schedule plus all its child occurrences (same recurring batch).
     *
     * @return Collection<int, Schedule>
     */
    public function findWithOccurrences(Schedule $schedule): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ScheduleSubRequest;

    /**
     * Acquire a row-level lock then re-fetch the request; throws ModelNotFoundException
     * if the row no longer exists.
     */
    public function findAndLock(int $id): ScheduleSubRequest;

    /** @param array<string, mixed> $attributes */
    public function createSubSsa(array $attributes): ScheduleSubSsa;

    /**
     * Find the accepted sub-SSA snapshot for a specific sub therapist on a schedule.
     */
    public function findSubSsaForSchedule(int $scheduleId, int $subTherapistId): ?ScheduleSubSsa;

    /**
     * Find all sub-SSA snapshots matching a performing therapist on a given date + service + student.
     * Used by the importer to resolve coverage when the normal SSA lookup fails.
     *
     * @return Collection<int, ScheduleSubSsa>
     */
    public function findSubSsasForImport(int $subTherapistId, string $sessionDate, int $serviceId, int $studentId): Collection;

    /**
     * Apply position-match + active-contract-for-service eligibility to a User Builder.
     * Used for: picker endpoint, store/update invitee validation, accept-time re-check.
     *
     * @param  Builder<User>  $users
     * @return Builder<User>
     */
    public function applyEligibilityFilter(Builder $users, Schedule $schedule): Builder;

    /**
     * Return the subset of $candidateIds that are eligible to cover $schedule.
     *
     * @param  array<int, int>  $candidateIds
     * @return array<int, int>
     */
    public function filterEligibleIds(array $candidateIds, Schedule $schedule): array;

    /**
     * Return eligible therapists for a schedule, each annotated with their current
     * invitee status on the schedule's open request (if any).
     * Status values: 'selected' (currently invited), 'declined', 'none'.
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsFor(Schedule $schedule): Collection;

    /**
     * Return eligible therapists for a not-yet-created schedule (create-time picker).
     * Eligibility is based on the requester's position + active contract for the service on the date.
     * No invitee-status annotation since no request exists yet.
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsForCreate(User $requester, int $serviceId, string $date): Collection;

    // ── Invitee row operations ─────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function createInvitee(array $attributes): ScheduleSubRequestInvitee;

    public function findInviteeRow(int $requestId, int $therapistId): ?ScheduleSubRequestInvitee;

    /**
     * Lock the matching invitee row for update; null when no row exists.
     */
    public function findAndLockInviteeRow(int $requestId, int $therapistId): ?ScheduleSubRequestInvitee;

    /**
     * Return all invitee rows for a request.
     *
     * @return Collection<int, ScheduleSubRequestInvitee>
     */
    public function getInviteesForRequest(int $requestId): Collection;

    /**
     * Bulk-update every invitee row on $requestId currently in $currentStatus
     * to $newStatus. Returns affected row count.
     */
    public function bulkUpdateInviteeStatus(
        int $requestId,
        SubRequestInviteeStatus $currentStatus,
        SubRequestInviteeStatus $newStatus,
    ): int;

    /**
     * Bulk-update every invitee row on $requestId in $currentStatus (except $exceptInviteeId)
     * to $newStatus. Used when one accepter wins and the rest must be superseded.
     */
    public function bulkSupersedeOtherInvitees(
        int $requestId,
        int $exceptInviteeId,
        SubRequestInviteeStatus $currentStatus = SubRequestInviteeStatus::INVITED,
    ): int;
}
