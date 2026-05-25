<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\Services;

use App\Domain\Schedule\Sub\DTOs\EligibleSubDTO;
use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Enums\ScheduleSubCoverageStatus;
use App\Enums\SubRequestInviteeStatus;
use App\Enums\SubRequestStatus;
use App\Events\ScheduleSubRequest\Withdrawn;
use App\Mail\ScheduleSubRequest\SubRequestInvitationMail;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class ScheduleSubRequestService
{
    public function __construct(
        private readonly ScheduleSubRequestRepositoryInterface $repository,
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly SessionLogRateService $rateService,
        private readonly UserTimezoneService $timezoneService,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Create a sub request for a single schedule, inserting an invitee row per ID.
     *
     * @param  array<int, int>  $inviteeIds
     */
    public function create(User $requester, Schedule $schedule, array $inviteeIds, ?string $reason): ScheduleSubRequest
    {
        if ((int) $schedule->therapist_id !== (int) $requester->id) {
            throw new \InvalidArgumentException('Only the assigned therapist can raise a sub request for this schedule.');
        }

        if (empty($inviteeIds)) {
            throw new \InvalidArgumentException('At least one invitee must be selected.');
        }

        $cutoffHours = (int) config('scheduling.sub_request_cutoff_hours', 2);
        // hoursUntilStart is negative when the session is already in the past.
        $hoursUntilStart = now()->diffInHours($schedule->startUtc(), false);
        if ($hoursUntilStart < $cutoffHours) {
            throw new \InvalidArgumentException(
                "Sub requests must be raised at least {$cutoffHours} hour(s) before the session starts."
            );
        }

        if ($this->repository->findOpenForSchedule($schedule->id) !== null) {
            throw new \InvalidArgumentException('An open sub request already exists for this schedule.');
        }

        $this->assertAllEligible($inviteeIds, $schedule);

        $subRequest = DB::transaction(function () use ($requester, $schedule, $inviteeIds, $reason): ScheduleSubRequest {
            $subRequest = $this->repository->create([
                'schedule_id' => $schedule->id,
                'requested_by_id' => $requester->id,
                'reason' => $reason,
                'status' => SubRequestStatus::OPEN->value,
            ]);

            foreach ($inviteeIds as $therapistId) {
                $this->repository->createInvitee([
                    'schedule_sub_request_id' => $subRequest->id,
                    'therapist_id' => $therapistId,
                    'status' => SubRequestInviteeStatus::INVITED->value,
                ]);
            }

            $schedule->update(['sub_request_status' => ScheduleSubCoverageStatus::REQUESTED->value]);

            return $subRequest;
        });

        $this->sendInvitationEmails($subRequest, $inviteeIds);

        return $subRequest;
    }

    /**
     * Sync the invitee list on an open request.
     *
     * @param  array<int, int>  $inviteeIds
     */
    public function syncInvitees(User $actor, ScheduleSubRequest $request, array $inviteeIds): void
    {
        if (! $request->isOpen()) {
            throw new \InvalidArgumentException('Invitees can only be managed while the request is open.');
        }

        $isOwner = (int) $actor->id === (int) $request->requested_by_id;
        $isAdmin = $actor->isAdmin();
        if (! $isOwner && ! $isAdmin) {
            throw new \InvalidArgumentException('You do not have permission to manage invitees for this request.');
        }

        if (empty($inviteeIds)) {
            throw new \InvalidArgumentException('At least one invitee must be selected.');
        }

        $schedule = $request->schedule;
        if ($schedule === null) {
            throw new \InvalidArgumentException('Schedule not found.');
        }

        $cutoffHours = (int) config('scheduling.sub_request_cutoff_hours', 2);
        if (now()->diffInHours($schedule->startUtc(), false) < $cutoffHours) {
            throw new \InvalidArgumentException(
                "Invitees cannot be changed within {$cutoffHours} hour(s) of the session."
            );
        }

        $this->assertAllEligible($inviteeIds, $schedule);

        $newlyInvitedIds = DB::transaction(function () use ($request, $inviteeIds): array {
            $existing = $this->repository->getInviteesForRequest($request->id)
                ->keyBy('therapist_id');

            $newIdSet = array_flip($inviteeIds);
            $newlyInvited = [];

            foreach ($inviteeIds as $therapistId) {
                $row = $existing->get($therapistId);

                if ($row === null) {
                    $this->repository->createInvitee([
                        'schedule_sub_request_id' => $request->id,
                        'therapist_id' => $therapistId,
                        'status' => SubRequestInviteeStatus::INVITED->value,
                    ]);
                    $newlyInvited[] = $therapistId;

                    continue;
                }

                if ($row->status === SubRequestInviteeStatus::DECLINED) {
                    $row->update(['status' => SubRequestInviteeStatus::INVITED->value, 'responded_at' => null]);
                    $newlyInvited[] = $therapistId;
                }
                // 'invited' + in payload → no-op; terminal statuses untouched
            }

            // IDs currently 'invited' but removed from the new payload → withdrawn
            foreach ($existing as $therapistId => $row) {
                if ($row->status === SubRequestInviteeStatus::INVITED && ! isset($newIdSet[$therapistId])) {
                    $row->update(['status' => SubRequestInviteeStatus::WITHDRAWN->value]);
                }
            }

            return $newlyInvited;
        });

        if (! empty($newlyInvitedIds)) {
            $this->sendInvitationEmails($request, $newlyInvitedIds);
        }
    }

    /**
     * Accept a sub request. The caller must have an `invited` invitee row.
     *
     * Locks the parent request row AND the invitee row before any state changes.
     * Every guard (status, cutoff, eligibility) is re-checked inside the transaction
     * so a concurrent accept/decline/syncInvitees cannot slip through.
     */
    public function accept(User $sub, ScheduleSubRequest $request): void
    {
        if ((int) $request->requested_by_id === (int) $sub->id) {
            throw new \InvalidArgumentException('You cannot accept your own sub request.');
        }

        $schedule = $request->schedule;
        if ($schedule === null) {
            throw new \InvalidArgumentException('Schedule not found.');
        }

        DB::transaction(function () use ($sub, $request, $schedule): void {
            $fresh = $this->repository->findAndLock($request->id);

            if (! $fresh->isOpen()) {
                throw new \InvalidArgumentException('This sub request was already accepted by someone else.');
            }

            $inviteeRow = $this->repository->findAndLockInviteeRow($fresh->id, $sub->id);
            if ($inviteeRow === null || $inviteeRow->status !== SubRequestInviteeStatus::INVITED) {
                throw new \InvalidArgumentException('You have not been invited to cover this session.');
            }

            // Cutoff guard (race safety) — re-checked inside the lock.
            $cutoffHours = (int) config('scheduling.sub_request_cutoff_hours', 2);
            if (now()->diffInHours($schedule->startUtc(), false) < $cutoffHours) {
                throw new \InvalidArgumentException(
                    "Sub requests can no longer be accepted within {$cutoffHours} hour(s) of the session."
                );
            }

            // Position match re-check
            $requester = $fresh->requestedBy;
            if ($requester === null) {
                throw new \InvalidArgumentException('Requester not found.');
            }
            $requesterPositionId = $requester->therapistProfile?->position_id;
            $subPositionId = $sub->therapistProfile?->position_id;
            if ($requesterPositionId === null || $subPositionId === null || $requesterPositionId !== $subPositionId) {
                throw new \InvalidArgumentException('You do not have the required position to cover this session.');
            }

            // Contract re-check (contracts can change between invite and accept)
            $sessionDate = $schedule->schedule_date->format('Y-m-d');
            $rate = $this->rateService->getTherapistRate($sub->id, $schedule->service_id, $sessionDate);
            if ($rate['contract_id'] === null) {
                throw new \InvalidArgumentException('You do not have an active contract covering this service on the session date.');
            }

            $ssa = $this->resolveOriginalSsa($schedule, $sessionDate);
            if ($ssa === null) {
                throw new \InvalidArgumentException('No active SSA found for the original therapist on this schedule.');
            }

            $fresh->update([
                'status' => SubRequestStatus::ACCEPTED->value,
                'accepted_by_id' => $sub->id,
                'accepted_at' => now(),
            ]);

            $inviteeRow->update([
                'status' => SubRequestInviteeStatus::ACCEPTED->value,
                'responded_at' => now(),
            ]);

            // Single-statement supersede of all OTHER still-invited invitees.
            $this->repository->bulkSupersedeOtherInvitees($fresh->id, $inviteeRow->id);

            $schedule->update([
                'sub_therapist_id' => $sub->id,
                'sub_request_status' => ScheduleSubCoverageStatus::ACCEPTED->value,
            ]);

            $this->repository->createSubSsa([
                'schedule_sub_request_id' => $fresh->id,
                'schedule_id' => $schedule->id,
                'ssa_id' => $ssa->id,
                'sub_therapist_id' => $sub->id,
                'student_id' => $schedule->student_id,
                'service_id' => $schedule->service_id,
                'school_id' => $schedule->school_id,
                'session_date' => $sessionDate,
            ]);
        });
    }

    /**
     * Decline an invitation. Only the invited therapist can decline.
     */
    public function decline(User $sub, ScheduleSubRequest $request): void
    {
        $inviteeRow = $this->repository->findInviteeRow($request->id, $sub->id);
        if ($inviteeRow === null || $inviteeRow->status !== SubRequestInviteeStatus::INVITED) {
            throw new \InvalidArgumentException('You do not have an active invitation for this request.');
        }

        $inviteeRow->update([
            'status' => SubRequestInviteeStatus::DECLINED->value,
            'responded_at' => now(),
        ]);
        // Parent request stays 'open' — other invitees can still accept.
    }

    /**
     * Cancel an open request. Flips all `invited` invitee rows to `superseded`.
     */
    public function cancel(User $actor, ScheduleSubRequest $request): void
    {
        if (! $request->isOpen()) {
            throw new \InvalidArgumentException('Only open sub requests can be cancelled.');
        }

        $isOwner = (int) $actor->id === (int) $request->requested_by_id;
        $isAdmin = $actor->isAdmin();
        if (! $isOwner && ! $isAdmin) {
            throw new \InvalidArgumentException('You do not have permission to cancel this sub request.');
        }

        DB::transaction(function () use ($request): void {
            $request->update([
                'status' => SubRequestStatus::CANCELLED->value,
                'cancelled_at' => now(),
            ]);

            $this->repository->bulkUpdateInviteeStatus(
                $request->id,
                SubRequestInviteeStatus::INVITED,
                SubRequestInviteeStatus::SUPERSEDED,
            );

            $schedule = $request->schedule;
            $schedule?->update([
                'sub_request_status' => null,
                'sub_therapist_id' => null,
            ]);
        });
    }

    /**
     * Withdraw an already-accepted request. Coverage is fully revoked: the request
     * becomes `withdrawn`, the accepting invitee row reverts to `superseded`, the
     * sub-SSA snapshot(s) are soft-deleted, and the schedule's sub-coverage columns
     * are cleared so the original therapist resumes the session. Once the transaction
     * commits, a Withdrawn event is fired so the covering therapist is notified via a
     * queued listener. Authorization is enforced by the policy at the controller; the
     * in-lock status re-check guards against a concurrent state change.
     */
    public function withdraw(ScheduleSubRequest $request): void
    {
        if (! $request->isAccepted()) {
            throw new \InvalidArgumentException('Only accepted sub requests can be withdrawn.');
        }

        $coveringTherapist = $request->acceptedBy;

        DB::transaction(function () use ($request): void {
            $fresh = $this->repository->findAndLock($request->id);

            if (! $fresh->isAccepted()) {
                throw new \InvalidArgumentException('This sub request can no longer be withdrawn.');
            }

            $fresh->update([
                'status' => SubRequestStatus::WITHDRAWN->value,
                'cancelled_at' => now(),
            ]);

            $this->repository->bulkUpdateInviteeStatus(
                $fresh->id,
                SubRequestInviteeStatus::ACCEPTED,
                SubRequestInviteeStatus::SUPERSEDED,
            );

            $this->repository->softDeleteSubSsasForRequest($fresh->id);

            $fresh->schedule?->update([
                'sub_request_status' => null,
                'sub_therapist_id' => null,
            ]);
        });

        if ($coveringTherapist !== null) {
            Withdrawn::dispatch($request, $coveringTherapist);
        }
    }

    /**
     * Mark open sub requests as `expired` when their schedule's start is within
     * `scheduling.sub_request_cutoff_hours`. Still-`invited` invitee rows are flipped
     * to `expired`. Processes requests in chunks; one transaction per chunk.
     *
     * @return int Number of parent requests expired.
     */
    public function expireOverdue(int $chunkSize = 100): int
    {
        $cutoffHours = (int) config('scheduling.sub_request_cutoff_hours', 2);
        $cutoff = now()->addHours($cutoffHours);

        $overdue = $this->repository->listOpenOverdue($cutoff);

        if ($overdue->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($overdue->chunk($chunkSize) as $chunk) {
            DB::transaction(function () use ($chunk, &$count): void {
                foreach ($chunk as $request) {
                    $request->update(['status' => SubRequestStatus::EXPIRED->value]);

                    $this->repository->bulkUpdateInviteeStatus(
                        $request->id,
                        SubRequestInviteeStatus::INVITED,
                        SubRequestInviteeStatus::EXPIRED,
                    );

                    $request->schedule?->update([
                        'sub_request_status' => null,
                        'sub_therapist_id' => null,
                    ]);

                    $count++;
                }
            });
        }

        return $count;
    }

    /**
     * Create sub requests for the given schedule and all its recurring occurrences.
     *
     * Each occurrence runs in its own transaction (via `create()`), so a later
     * failure does NOT roll back occurrences that already succeeded — the
     * recurring-batch contract is "best effort after the first". The first
     * occurrence is required: its failure propagates as an InvalidArgumentException
     * so the controller can surface the reason (e.g. cutoff exceeded).
     *
     * @param  array<int, int>  $inviteeIds
     * @return array{created: int, skipped: int}
     */
    public function createForScheduleAndOccurrences(User $requester, Schedule $schedule, array $inviteeIds, ?string $reason): array
    {
        $isRecurring = $schedule->parent_schedule_id === null
            && $schedule->recurrence_type?->value !== 'none';

        $occurrences = $isRecurring
            ? $this->repository->findWithOccurrences($schedule)
            : collect([$schedule]);

        $created = 0;
        $skipped = 0;
        $first = true;
        foreach ($occurrences as $occurrence) {
            try {
                $this->create($requester, $occurrence, $inviteeIds, $reason);
                $created++;
            } catch (\InvalidArgumentException $e) {
                if ($first) {
                    throw $e;
                }
                $skipped++;
                Log::warning('ScheduleSubRequestService: skipped occurrence', [
                    'schedule_id' => $occurrence->id,
                    'reason' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                Log::error('ScheduleSubRequestService: sub request creation failed', [
                    'schedule_id' => $occurrence->id,
                    'exception' => $e,
                ]);
            }
            $first = false;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return Collection<int, ScheduleSubRequest> */
    public function listOpenForTherapist(User $sub): Collection
    {
        return $this->repository->listOpenForTherapist($sub);
    }

    /**
     * Paginated variant for DataTables.
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function pageOpenForTherapist(User $sub, int $offset, int $limit): Collection
    {
        return $this->repository->pageOpenForTherapist($sub, $offset, $limit);
    }

    public function countOpenForTherapist(User $sub): int
    {
        return $this->repository->countOpenForTherapist($sub);
    }

    /**
     * Count open requests raised by this therapist (for the "My sub requests" dashboard badge).
     */
    public function countMyOpenRequests(User $requester): int
    {
        return $this->repository->countMyOpenRequests($requester);
    }

    /**
     * Requester-side list for the "My Requests" tab: open + accepted requests
     * whose schedule has not yet started in the requester's local timezone.
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listAsRequester(User $requester): Collection
    {
        [$startOfTodayUtc] = $this->timezoneService->userDayUtcRange(now(), $requester);

        return $this->repository->listAsRequester($requester, $startOfTodayUtc);
    }

    /**
     * Paginated variant for DataTables.
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function pageAsRequester(User $requester, int $offset, int $limit): Collection
    {
        [$startOfTodayUtc] = $this->timezoneService->userDayUtcRange(now(), $requester);

        return $this->repository->pageAsRequester($requester, $startOfTodayUtc, $offset, $limit);
    }

    public function countAsRequester(User $requester): int
    {
        [$startOfTodayUtc] = $this->timezoneService->userDayUtcRange(now(), $requester);

        return $this->repository->countAsRequester($requester, $startOfTodayUtc);
    }

    /**
     * Return eligible therapists for a schedule annotated with their invitee status.
     *
     * @return Collection<int, EligibleSubDTO>
     */
    public function listEligibleSubsFor(Schedule $schedule): Collection
    {
        return $this->repository->listEligibleSubsFor($schedule);
    }

    /**
     * Eligible therapists for the create-time picker (no schedule row yet).
     *
     * @return Collection<int, EligibleSubDTO>
     */
    public function listEligibleSubsForCreate(User $requester, int $serviceId, string $date): Collection
    {
        return $this->repository->listEligibleSubsForCreate($requester, $serviceId, $date);
    }

    /**
     * Send invitation emails to the given invitees. Failures are logged and swallowed
     * so a mailer outage cannot fail the primary request-creation action.
     *
     * @param  array<int, int>  $inviteeIds
     */
    private function sendInvitationEmails(ScheduleSubRequest $request, array $inviteeIds): void
    {
        $invitees = $this->userRepository->findByIds($inviteeIds);

        foreach ($invitees as $invitee) {
            if (empty($invitee->email)) {
                continue;
            }

            try {
                Mail::to($invitee->email)->queue(new SubRequestInvitationMail($request, $invitee));
            } catch (\Throwable $e) {
                Log::error('ScheduleSubRequestService: failed to send invitation mail', [
                    'sub_request_id' => $request->id,
                    'invitee_id' => $invitee->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveOriginalSsa(Schedule $schedule, string $sessionDate): ?ServiceSupportAgreement
    {
        return $this->ssaRepository->findOriginalSsaForSubCoverage(
            $schedule->student_id,
            $schedule->service_id,
            $schedule->therapist_id,
            $sessionDate,
        );
    }

    /**
     * Throws if any ID in the list is not eligible for the given schedule.
     *
     * @param  array<int, int>  $inviteeIds
     */
    private function assertAllEligible(array $inviteeIds, Schedule $schedule): void
    {
        $eligibleIds = $this->repository->filterEligibleIds($inviteeIds, $schedule);

        $ineligible = array_diff($inviteeIds, $eligibleIds);
        if (! empty($ineligible)) {
            throw new \InvalidArgumentException('One or more selected therapists are not eligible to cover this session.');
        }
    }
}
