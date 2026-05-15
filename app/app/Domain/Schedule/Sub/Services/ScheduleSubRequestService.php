<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\Services;

use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ScheduleSubRequestService
{
    public function __construct(
        private readonly ScheduleSubRequestRepositoryInterface $repository,
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly SessionLogRateService $rateService,
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

        return DB::transaction(function () use ($requester, $schedule, $inviteeIds, $reason): ScheduleSubRequest {
            $subRequest = $this->repository->create([
                'schedule_id' => $schedule->id,
                'requested_by_id' => $requester->id,
                'reason' => $reason,
                'status' => 'open',
            ]);

            foreach ($inviteeIds as $therapistId) {
                $this->repository->createInvitee([
                    'schedule_sub_request_id' => $subRequest->id,
                    'therapist_id' => $therapistId,
                    'status' => 'invited',
                ]);
            }

            $schedule->update(['sub_request_status' => 'requested']);

            return $subRequest;
        });
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
        $isAdmin = $actor->role->value === 'admin';
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

        DB::transaction(function () use ($request, $inviteeIds): void {
            $existing = $this->repository->getInviteesForRequest($request->id)
                ->keyBy('therapist_id');

            $newIdSet = array_flip($inviteeIds);

            foreach ($inviteeIds as $therapistId) {
                $row = $existing->get($therapistId);

                if ($row === null) {
                    $this->repository->createInvitee([
                        'schedule_sub_request_id' => $request->id,
                        'therapist_id' => $therapistId,
                        'status' => 'invited',
                    ]);

                    continue;
                }

                if ($row->status === 'declined') {
                    $row->update(['status' => 'invited', 'responded_at' => null]);
                }
                // 'invited' + in payload → no-op; terminal statuses untouched
            }

            // IDs currently 'invited' but removed from the new payload → withdrawn
            foreach ($existing as $therapistId => $row) {
                if ($row->status === 'invited' && ! isset($newIdSet[$therapistId])) {
                    $row->update(['status' => 'withdrawn']);
                }
            }
        });
    }

    /**
     * Accept a sub request. The caller must have an `invited` invitee row.
     * Uses lockForUpdate to guarantee only the first accepter wins.
     */
    public function accept(User $sub, ScheduleSubRequest $request): void
    {
        if (! $request->isOpen()) {
            throw new \InvalidArgumentException('This sub request is no longer open.');
        }

        if ((int) $request->requested_by_id === (int) $sub->id) {
            throw new \InvalidArgumentException('You cannot accept your own sub request.');
        }

        $inviteeRow = $this->repository->findInviteeRow($request->id, $sub->id);
        if ($inviteeRow === null || $inviteeRow->status !== 'invited') {
            throw new \InvalidArgumentException('You have not been invited to cover this session.');
        }

        $schedule = $request->schedule;
        if ($schedule === null) {
            throw new \InvalidArgumentException('Schedule not found.');
        }

        // Position match re-check
        $requester = $request->requestedBy;
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

        // Cutoff guard (race safety)
        $cutoffHours = (int) config('scheduling.sub_request_cutoff_hours', 2);
        if (now()->diffInHours($schedule->startUtc(), false) < $cutoffHours) {
            throw new \InvalidArgumentException(
                "Sub requests can no longer be accepted within {$cutoffHours} hour(s) of the session."
            );
        }

        $ssa = $this->resolveOriginalSsa($schedule, $sessionDate);
        if ($ssa === null) {
            throw new \InvalidArgumentException('No active SSA found for the original therapist on this schedule.');
        }

        DB::transaction(function () use ($sub, $request, $schedule, $ssa, $sessionDate, $inviteeRow): void {
            $fresh = $this->repository->findAndLock($request->id);

            if (! $fresh->isOpen()) {
                throw new \InvalidArgumentException('This sub request was already accepted by someone else.');
            }

            $fresh->update([
                'status' => 'accepted',
                'accepted_by_id' => $sub->id,
                'accepted_at' => now(),
            ]);

            // Accept this invitee row
            $inviteeRow->update(['status' => 'accepted', 'responded_at' => now()]);

            // Supersede all other remaining invitee rows
            $this->repository->getInviteesForRequest($fresh->id)
                ->reject(fn ($row) => $row->id === $inviteeRow->id || $row->status === 'accepted')
                ->each(fn ($row) => $row->update(['status' => 'superseded']));

            $schedule->update([
                'sub_therapist_id' => $sub->id,
                'sub_request_status' => 'accepted',
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
        if ($inviteeRow === null || $inviteeRow->status !== 'invited') {
            throw new \InvalidArgumentException('You do not have an active invitation for this request.');
        }

        $inviteeRow->update(['status' => 'declined', 'responded_at' => now()]);
        // Parent request stays 'open' — other invitees can still accept.
    }

    /**
     * Cancel an open or accepted request. Flips all `invited` invitee rows to `superseded`.
     */
    public function cancel(User $actor, ScheduleSubRequest $request): void
    {
        if (! $request->isOpen()) {
            throw new \InvalidArgumentException('Only open sub requests can be cancelled.');
        }

        $isOwner = (int) $actor->id === (int) $request->requested_by_id;
        $isAdmin = $actor->role->value === 'admin';
        if (! $isOwner && ! $isAdmin) {
            throw new \InvalidArgumentException('You do not have permission to cancel this sub request.');
        }

        DB::transaction(function () use ($request): void {
            $request->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Flip all invited rows to superseded
            $this->repository->getInviteesForRequest($request->id)
                ->where('status', 'invited')
                ->each(fn ($row) => $row->update(['status' => 'superseded']));

            $schedule = $request->schedule;
            $schedule?->update([
                'sub_request_status' => null,
                'sub_therapist_id' => null,
            ]);
        });
    }

    /**
     * Create sub requests for the given schedule and all its recurring occurrences.
     *
     * @param  array<int, int>  $inviteeIds
     */
    public function createForScheduleAndOccurrences(User $requester, Schedule $schedule, array $inviteeIds, ?string $reason): void
    {
        $isRecurring = $schedule->parent_schedule_id === null
            && $schedule->recurrence_type?->value !== 'none';

        $occurrences = $isRecurring
            ? $this->repository->findWithOccurrences($schedule)
            : collect([$schedule]);

        $first = true;
        foreach ($occurrences as $occurrence) {
            try {
                $this->create($requester, $occurrence, $inviteeIds, $reason);
            } catch (\InvalidArgumentException $e) {
                if ($first) {
                    // Propagate the first validation failure so the controller can
                    // surface a warning to the therapist (e.g. cutoff exceeded).
                    throw $e;
                }
                // Subsequent occurrences: log and continue so one bad date doesn't
                // block the rest of a recurring series.
                Log::warning('ScheduleSubRequestService: skipped occurrence', [
                    'schedule_id' => $occurrence->id,
                    'reason' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::error('ScheduleSubRequestService: sub request creation failed', [
                    'schedule_id' => $occurrence->id,
                    'exception' => $e,
                ]);
            }
            $first = false;
        }
    }

    /** @return Collection<int, ScheduleSubRequest> */
    public function listOpenForTherapist(User $sub): Collection
    {
        return $this->repository->listOpenForTherapist($sub);
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
     * Return eligible therapists for a schedule annotated with their invitee status.
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsFor(Schedule $schedule): Collection
    {
        return $this->repository->listEligibleSubsFor($schedule);
    }

    /**
     * Eligible therapists for the create-time picker (no schedule row yet).
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsForCreate(User $requester, int $serviceId, string $date): Collection
    {
        return $this->repository->listEligibleSubsForCreate($requester, $serviceId, $date);
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
        $eligibleIds = $this->repository
            ->applyEligibilityFilter(
                \App\Models\User::query(),
                $schedule
            )
            ->whereIn('id', $inviteeIds)
            ->pluck('id')
            ->all();

        $ineligible = array_diff($inviteeIds, $eligibleIds);
        if (! empty($ineligible)) {
            throw new \InvalidArgumentException('One or more selected therapists are not eligible to cover this session.');
        }
    }
}
