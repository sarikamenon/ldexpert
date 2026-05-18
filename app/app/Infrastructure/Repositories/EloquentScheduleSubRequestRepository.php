<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
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
        return ScheduleSubRequest::query()
            ->open()
            ->invitedTo($sub)
            ->with(['schedule', 'schedule.student', 'schedule.service', 'schedule.school', 'requestedBy'])
            ->get();
    }

    public function countOpenForTherapist(User $sub): int
    {
        return ScheduleSubRequest::query()
            ->open()
            ->invitedTo($sub)
            ->count();
    }

    public function countMyOpenRequests(User $requester): int
    {
        return ScheduleSubRequest::query()
            ->open()
            ->where('requested_by_id', $requester->id)
            ->count();
    }

    /**
     * @return Collection<int, ScheduleSubRequest>
     */
    public function listAsRequester(User $requester, CarbonInterface $startTimeAtOrAfter): Collection
    {
        return ScheduleSubRequest::query()
            ->whereIn('status', ['open', 'accepted'])
            ->where('requested_by_id', $requester->id)
            ->whereHas('schedule', function (Builder $q) use ($startTimeAtOrAfter): void {
                $q->whereRaw('TIMESTAMP(schedule_date, start_time) >= ?', [
                    $startTimeAtOrAfter->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'),
                ]);
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
                $q->whereRaw('TIMESTAMP(schedule_date, start_time) <= ?', [
                    $cutoff->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'),
                ]);
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

        // Must not be the requester themselves.
        $users->where('users.id', '!=', $schedule->therapist_id);

        // Must share the same position as the requester.
        $users->whereHas('therapistProfile', function (Builder $q) use ($requesterPositionId): void {
            $q->where('position_id', $requesterPositionId); // @phpstan-ignore argument.type
        });

        // Must have an active contract covering the schedule's service on schedule_date.
        $scheduleDate = $schedule->schedule_date->format('Y-m-d');
        $serviceId = $schedule->service_id;

        $users->whereHas('therapistProfile.contracts', function (Builder $q) use ($scheduleDate, $serviceId): void {
            $q->where('status', 'active') // @phpstan-ignore argument.type
                ->where('start_date', '<=', $scheduleDate) // @phpstan-ignore argument.type
                ->where(function (Builder $end) use ($scheduleDate): void {
                    $end->whereNull('end_date')
                        ->orWhere('end_date', '>=', $scheduleDate); // @phpstan-ignore argument.type
                })
                ->whereHas('services', function (Builder $svc) use ($serviceId): void {
                    $svc->where('service_id', $serviceId); // @phpstan-ignore argument.type
                });
        });

        return $users;
    }

    /**
     * Return eligible therapists annotated with invitee_status: 'selected', 'declined', or 'none'.
     *
     * @return Collection<int, User>
     */
    public function listEligibleSubsFor(Schedule $schedule): Collection
    {
        $openRequest = $this->findOpenForSchedule($schedule->id);

        // Load existing invitee rows keyed by therapist_id for annotation.
        /** @var array<int, string> $inviteeStatuses */
        $inviteeStatuses = [];
        if ($openRequest !== null) {
            $inviteeStatuses = ScheduleSubRequestInvitee::query()
                ->where('schedule_sub_request_id', $openRequest->id)
                ->pluck('status', 'therapist_id')
                ->all();
        }

        $users = $this->applyEligibilityFilter(
            User::query()->with('therapistProfile'),
            $schedule
        )->get();

        return $users->map(function (User $user) use ($inviteeStatuses): User {
            $raw = $inviteeStatuses[$user->id] ?? 'none';
            // Map internal status → picker-facing annotation.
            $user->invitee_status = match ($raw) { // @phpstan-ignore property.notFound
                'invited' => 'selected',
                'declined' => 'declined',
                default => 'none',
            };

            return $user;
        });
    }

    /**
     * Return eligible therapists for a schedule that does not yet exist (create-time picker).
     * Runs the same position + contract filter as applyEligibilityFilter but takes raw
     * values instead of a Schedule model so no schedule row is needed.
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
            ->where('users.id', '!=', $requester->id)
            ->whereHas('therapistProfile', function (Builder $q) use ($positionId): void {
                $q->where('position_id', $positionId); // @phpstan-ignore argument.type
            })
            ->whereHas('therapistProfile.contracts', function (Builder $q) use ($date, $serviceId): void {
                $q->where('status', 'active') // @phpstan-ignore argument.type
                    ->where('start_date', '<=', $date) // @phpstan-ignore argument.type
                    ->where(function (Builder $end) use ($date): void {
                        $end->whereNull('end_date')
                            ->orWhere('end_date', '>=', $date); // @phpstan-ignore argument.type
                    })
                    ->whereHas('services', function (Builder $svc) use ($serviceId): void {
                        $svc->where('service_id', $serviceId); // @phpstan-ignore argument.type
                    });
            })
            ->get();

        return $users->map(function (User $user): User {
            $user->invitee_status = 'none'; // @phpstan-ignore property.notFound
            return $user;
        });
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
            ->where('schedule_sub_request_id', $requestId)
            ->where('therapist_id', $therapistId)
            ->first();
    }

    /** @return Collection<int, ScheduleSubRequestInvitee> */
    public function getInviteesForRequest(int $requestId): Collection
    {
        return ScheduleSubRequestInvitee::query()
            ->where('schedule_sub_request_id', $requestId)
            ->get();
    }
}
