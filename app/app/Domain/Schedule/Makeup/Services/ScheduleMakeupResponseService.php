<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\DTOs\Schedule\Makeup\RecordMakeupResponseDTO;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Exceptions\MakeupResponseNotAllowedException;
use App\Models\ScheduleMakeupRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records a parent's response (request / decline) to a make-up reminder.
 *
 * The unit of work is a **batch** — every row that shares a `response_token`
 * gets updated together, since one email covers every missed session for the
 * same (calendar event, student, therapist). One click flips the whole batch.
 */
final class ScheduleMakeupResponseService
{
    public function __construct(
        private readonly ScheduleMakeupRequestRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function findBatchByToken(string $token): Collection
    {
        return $this->repository->findBatchByResponseToken($token);
    }

    /**
     * Apply a "Request Make-Up" click to every row in the batch.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function recordParentRequest(Collection $batch, ?CarbonImmutable $now = null): Collection
    {
        return $this->applyParentResponse(
            batch: $batch,
            buildDto: fn (int $requestId, int $parentUserId, CarbonImmutable $clock): RecordMakeupResponseDTO => RecordMakeupResponseDTO::parentRequest($requestId, $parentUserId, $clock),
            now: $now,
        );
    }

    /**
     * Apply a "Decline Make-Up" click to every row in the batch.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function recordParentDecline(
        Collection $batch,
        ?string $reason = null,
        ?CarbonImmutable $now = null,
    ): Collection {
        return $this->applyParentResponse(
            batch: $batch,
            buildDto: fn (int $requestId, int $parentUserId, CarbonImmutable $clock): RecordMakeupResponseDTO => RecordMakeupResponseDTO::parentDecline($requestId, $parentUserId, $reason, $clock),
            now: $now,
        );
    }

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     * @param  \Closure(int, int, CarbonImmutable): RecordMakeupResponseDTO  $buildDto
     * @return Collection<int, ScheduleMakeupRequest>
     */
    private function applyParentResponse(
        Collection $batch,
        \Closure $buildDto,
        ?CarbonImmutable $now,
    ): Collection {
        if ($batch->isEmpty()) {
            throw new MakeupResponseNotAllowedException(
                MakeupResponseNotAllowedException::REASON_BAD_STATE,
                'Empty batch — token does not resolve to any rows.',
            );
        }

        $clock = $now ?? CarbonImmutable::now();

        return DB::transaction(function () use ($batch, $buildDto, $clock): Collection {
            $locked = $batch
                ->map(fn (ScheduleMakeupRequest $row): ScheduleMakeupRequest => $this->repository->findAndLock($row->id))
                ->values();

            // Gate once on the batch's invariant fields (deadline, event-past,
            // bad state). Every row in a batch shares student/therapist/event
            // so the guards are uniform — except event_date, which we check
            // per-row below.
            $head = $locked->first();
            if ($head === null) {
                throw new MakeupResponseNotAllowedException(
                    MakeupResponseNotAllowedException::REASON_BAD_STATE,
                    'Batch resolved to no rows after locking.',
                );
            }

            $this->guardBatchCanRespond($locked, $head, $clock);

            $responderUserId = (int) $head->student_id;

            return $locked->map(function (ScheduleMakeupRequest $row) use ($buildDto, $responderUserId, $clock): ScheduleMakeupRequest {
                $dto = $buildDto($row->id, $responderUserId, $clock);

                return $this->repository->recordResponse($row, $dto);
            })->values();
        });
    }

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function guardBatchCanRespond(Collection $batch, ScheduleMakeupRequest $head, CarbonImmutable $now): void
    {
        if ($head->isResponded()) {
            throw new MakeupResponseNotAllowedException(
                MakeupResponseNotAllowedException::REASON_ALREADY_RESPONDED,
            );
        }

        if ($head->status !== ScheduleMakeupRequestStatus::SENT) {
            throw new MakeupResponseNotAllowedException(
                MakeupResponseNotAllowedException::REASON_BAD_STATE,
                "Batch head row is in state '{$head->status->value}'; only 'sent' rows accept responses.",
            );
        }

        $today = $now->startOfDay();

        if ($today->greaterThan(CarbonImmutable::parse($head->response_date->toDateString()))) {
            throw new MakeupResponseNotAllowedException(
                MakeupResponseNotAllowedException::REASON_DEADLINE_PASSED,
            );
        }

        // event_date is per-row; if *every* row is already in the past, refuse.
        $anyFuture = $batch->contains(
            fn (ScheduleMakeupRequest $row): bool => $today->lessThanOrEqualTo(CarbonImmutable::parse($row->event_date->toDateString()))
        );

        if (! $anyFuture) {
            throw new MakeupResponseNotAllowedException(
                MakeupResponseNotAllowedException::REASON_EVENT_PAST,
            );
        }
    }
}
