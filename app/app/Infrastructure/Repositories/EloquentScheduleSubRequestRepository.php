<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
use App\Enums\SubRequestInviteeStatus;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use App\Models\ScheduleSubSsa;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentScheduleSubRequestRepository implements ScheduleSubRequestRepositoryInterface
{
    /**
     * Returns open requests where the given therapist has an `invited` invitee row.
     *
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listOpenForTherapist(User $sub): Collection
    {
        return $this->openForTherapistQuery($sub)
            ->with(['schedule', 'schedule.student', 'schedule.service', 'schedule.school', 'requestedBy'])
            ->get();
    }

    public function countOpenForTherapist(User $sub): int
    {
        return $this->openForTherapistQuery($sub)->count();
    }

    public function countMyOpenRequests(User $requester): int
    {
        return ScheduleSubRequest::query()
            ->open()
            ->requestedBy($requester)
            ->count();
    }

    /**
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listAsRequester(User $requester, CarbonInterface $startTimeAtOrAfter): Collection
    {
        return ScheduleSubRequest::query()
            ->active()
            ->requestedBy($requester)
            ->whereHas('schedule', function (Builder $q) use ($startTimeAtOrAfter): void {
                $q->startingAfter($startTimeAtOrAfter->copy()->subSecond()); // @phpstan-ignore method.notFound
            })
            ->with([
                'schedule',
                'schedule.student',
                'schedule.service',
                'schedule.school',
                'invitees.therapist',
            ])
            ->get();
    }

    public function findOpenForSchedule(int $scheduleId): ?ScheduleSubRequest
    {
        return ScheduleSubRequest::query()
            ->open()
            ->forSchedule($scheduleId)
            ->first();
    }

    /**
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listOpenOverdue(CarbonInterface $cutoff): Collection
    {
        return ScheduleSubRequest::query()
            ->open()
            ->whereHas('schedule', function (Builder $q) use ($cutoff): void {
                $q->startingAtOrBefore($cutoff); // @phpstan-ignore method.notFound
            })
            ->with('schedule')
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ScheduleSubRequest
    {
        return ScheduleSubRequest::create($attributes);
    }

    public function findAndLock(int $id): ScheduleSubRequest
    {
        /** @var ScheduleSubRequest */
        return ScheduleSubRequest::query()->lockForUpdate()->findOrFail($id);
    }

    /** @param array<string, mixed> $attributes */
    public function createSubSsa(array $attributes): ScheduleSubSsa
    {
        return ScheduleSubSsa::create($attributes);
    }

    public function findSubSsaForSchedule(int $scheduleId, int $subTherapistId): ?ScheduleSubSsa
    {
        return ScheduleSubSsa::query()
            ->forSchedule($scheduleId)
            ->forSubTherapist($subTherapistId)
            ->first();
    }

    /** @return Collection<int, ScheduleSubSsa> */
    public function findSubSsasForImport(int $subTherapistId, string $sessionDate, int $serviceId, int $studentId): Collection
    {
        return ScheduleSubSsa::query()
            ->forSubTherapist($subTherapistId)
            ->forSessionDate($sessionDate)
            ->forService($serviceId)
            ->forStudent($studentId)
            ->with(['ssa', 'ssa.assignedTherapist'])
            ->get();
    }

    /** @return Collection<int, Schedule> */
    public function findWithOccurrences(Schedule $schedule): Collection
    {
        return Schedule::query()
            ->forParentOrSelf($schedule)
            ->get();
    }

    /**
     * Apply position-match + active-contract-for-service eligibility to a User Builder.
     *
     * @param  Builder<User>  $users
     * @return Builder<User>
     */
    public function applyEligibilityFilter(Builder $users, Schedule $schedule): Builder
    {
        $requester = User::find($schedule->therapist_id);
        $requesterPositionId = $requester?->therapistProfile?->position_id;

        if ($requesterPositionId === null) {
            return $users->whereRaw('0=1');
        }

        return $users->eligibleAsSubFor(
            (int) $schedule->therapist_id,
            $requesterPositionId,
            (int) $schedule->service_id,
            $schedule->schedule_date->format('Y-m-d'),
        );
    }

    /**
     * @param  array<int, int>  $candidateIds
     * @return array<int, int>
     */
    public function filterEligibleIds(array $candidateIds, Schedule $schedule): array
    {
        if (empty($candidateIds)) {
            return [];
        }

        /** @var array<int, int> */
        return $this->applyEligibilityFilter(User::query(), $schedule)
            ->whereIn('id', $candidateIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Return eligible therapists annotated with invitee_status: 'selected', 'declined', or 'none'.
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsFor(Schedule $schedule): Collection
    {
        $openRequest = $this->findOpenForSchedule($schedule->id);
        $inviteeStatuses = $this->loadInviteeStatusMap($openRequest);

        $users = $this->applyEligibilityFilter(
            User::query()->with('therapistProfile'),
            $schedule
        )->get();

        return $users->map(fn (User $user): User => $this->annotateInviteeStatus($user, $inviteeStatuses));
    }

    /**
     * Return eligible therapists for a schedule that does not yet exist (create-time picker).
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsForCreate(User $requester, int $serviceId, string $date): Collection
    {
        $positionId = $requester->therapistProfile?->position_id;

        if ($positionId === null) {
            return collect();
        }

        $users = User::query()
            ->with('therapistProfile')
            ->eligibleAsSubFor((int) $requester->id, $positionId, $serviceId, $date)
            ->get();

        return $users->map(fn (User $user): User => $this->annotateInviteeStatus($user, []));
    }

    // ── Invitee row operations ─────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function createInvitee(array $attributes): ScheduleSubRequestInvitee
    {
        return ScheduleSubRequestInvitee::create($attributes);
    }

    public function findInviteeRow(int $requestId, int $therapistId): ?ScheduleSubRequestInvitee
    {
        return ScheduleSubRequestInvitee::query()
            ->forRequest($requestId)
            ->where('therapist_id', $therapistId)
            ->first();
    }

    public function findAndLockInviteeRow(int $requestId, int $therapistId): ?ScheduleSubRequestInvitee
    {
        return ScheduleSubRequestInvitee::query()
            ->forRequest($requestId)
            ->where('therapist_id', $therapistId)
            ->lockForUpdate()
            ->first();
    }

    /** @return Collection<int, ScheduleSubRequestInvitee> */
    public function getInviteesForRequest(int $requestId): Collection
    {
        return ScheduleSubRequestInvitee::query()
            ->forRequest($requestId)
            ->get();
    }

    public function bulkUpdateInviteeStatus(
        int $requestId,
        SubRequestInviteeStatus $currentStatus,
        SubRequestInviteeStatus $newStatus,
    ): int {
        return ScheduleSubRequestInvitee::query()
            ->forRequest($requestId)
            ->withStatus($currentStatus)
            ->update(['status' => $newStatus->value]);
    }

    public function bulkSupersedeOtherInvitees(
        int $requestId,
        int $exceptInviteeId,
        SubRequestInviteeStatus $currentStatus = SubRequestInviteeStatus::INVITED,
    ): int {
        return ScheduleSubRequestInvitee::query()
            ->forRequest($requestId)
            ->exceptInvitee($exceptInviteeId)
            ->withStatus($currentStatus)
            ->update(['status' => SubRequestInviteeStatus::SUPERSEDED->value]);
    }

    // ── Internal helpers ───────────────────────────────────────────────────

    /**
     * @return Builder<ScheduleSubRequest>
     */
    private function openForTherapistQuery(User $sub): Builder
    {
        $cutoffHours = (int) config('scheduling.sub_request_cutoff_hours', 2);
        $earliestStart = now()->addHours($cutoffHours);

        return ScheduleSubRequest::query()
            ->open()
            ->invitedTo($sub)
            ->whereHas('schedule', function (Builder $q) use ($earliestStart): void {
                $q->startingAfter($earliestStart); // @phpstan-ignore method.notFound
            });
    }

    /**
     * Load existing invitee statuses keyed by therapist_id for picker annotation.
     *
     * @return array<int, string>
     */
    private function loadInviteeStatusMap(?ScheduleSubRequest $openRequest): array
    {
        if ($openRequest === null) {
            return [];
        }

        /** @var array<int, string> */
        return ScheduleSubRequestInvitee::query()
            ->forRequest($openRequest->id)
            ->pluck('status', 'therapist_id')
            ->all();
    }

    /**
     * Annotate the picker-facing invitee_status property on an eligible user.
     *
     * @param  array<int, string>  $inviteeStatuses
     */
    private function annotateInviteeStatus(User $user, array $inviteeStatuses): User
    {
        $raw = $inviteeStatuses[$user->id] ?? null;
        $user->invitee_status = match ($raw) { // @phpstan-ignore property.notFound
            SubRequestInviteeStatus::INVITED->value => 'selected',
            SubRequestInviteeStatus::DECLINED->value => 'declined',
            default => 'none',
        };

        return $user;
    }
}
