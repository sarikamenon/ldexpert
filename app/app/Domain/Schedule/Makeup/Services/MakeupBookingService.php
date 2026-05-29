<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\DTOs\OverlapCheckDTO;
use App\DTOs\OverlapExclusionsDTO;
use App\DTOs\Schedule\Makeup\MakeupSlotPickDTO;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use App\Models\ScheduleMakeupRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Commits a parent's sub-slot pick for Path 1 self-reschedule.
 *
 * Each pick reschedules the original missed `schedules` row in place —
 * updating its date/time to the chosen sub-slot. The booking is
 * concurrency-safe: inside a transaction, the chosen slot is re-checked
 * for overlap before writing.
 */
final class MakeupBookingService
{
    public function __construct(
        private readonly ScheduleMakeupRequestRepositoryInterface $makeupRequestRepo,
        private readonly ScheduleRepositoryInterface $scheduleRepo,
        private readonly ScheduleMakeupAvailabilityRepositoryInterface $availabilityRepo,
        private readonly MakeupSlotCalculator $slotCalculator,
    ) {}

    /**
     * Book a single sub-slot pick for one makeup request row.
     *
     * @throws InvalidArgumentException if the row is not in a bookable state
     * @throws MakeupSlotConflictException if the chosen slot was taken (race loss)
     */
    public function bookSlot(
        ScheduleMakeupRequest $makeupRequest,
        MakeupSlotPickDTO $pick,
        int $actorUserId,
    ): ScheduleMakeupRequest {
        if (! in_array($makeupRequest->status, [ScheduleMakeupRequestStatus::SENT, ScheduleMakeupRequestStatus::REQUESTED], true)) {
            throw new InvalidArgumentException(
                "Makeup request {$makeupRequest->id} is in state '{$makeupRequest->status->value}'; only 'sent' or 'requested' rows can be booked.",
            );
        }

        return DB::transaction(function () use ($makeupRequest, $pick, $actorUserId): ScheduleMakeupRequest {
            $locked = $this->makeupRequestRepo->findAndLock($makeupRequest->id);

            if (! in_array($locked->status, [ScheduleMakeupRequestStatus::SENT, ScheduleMakeupRequestStatus::REQUESTED], true)) {
                throw new InvalidArgumentException(
                    "Makeup request {$locked->id} changed state to '{$locked->status->value}' during booking.",
                );
            }

            $schedule = $locked->schedule;
            if ($schedule === null) {
                throw new InvalidArgumentException(
                    "Makeup request {$locked->id} has no linked schedule — cannot reschedule in place.",
                );
            }

            /** @var User $therapist */
            $therapist = $locked->therapist;

            $overlapCheck = new OverlapCheckDTO(
                date: $pick->date(),
                startTime: $pick->startTime(),
                endTime: $pick->endTime(),
            );
            $exclusions = new OverlapExclusionsDTO(scheduleId: $schedule->id);

            if ($this->scheduleRepo->hasOverlap($therapist, $overlapCheck, $exclusions)) {
                throw new MakeupSlotConflictException(
                    'The selected time slot was just taken. Please pick another.',
                );
            }

            /** @var User $student */
            $student = $locked->student;
            if ($this->scheduleRepo->hasOverlap($student, $overlapCheck, $exclusions)) {
                throw new MakeupSlotConflictException(
                    'The student already has a session at this time. Please pick another slot.',
                );
            }

            $schedule->fill([
                'schedule_date' => $pick->date(),
                'start_time' => $pick->startTime(),
                'end_time' => $pick->endTime(),
                'updated_by' => $actorUserId,
            ]);
            $schedule->save();

            return $this->makeupRequestRepo->linkBookedSchedule($locked, $schedule->id);
        });
    }

    /**
     * Book multiple picks in a single transaction (multi-day batch).
     * Each pick targets a different makeup request row.
     *
     * @param  array<int, array{request: ScheduleMakeupRequest, pick: MakeupSlotPickDTO}>  $bookings
     * @return Collection<int, ScheduleMakeupRequest>
     *
     * @throws MakeupSlotConflictException on any conflict (entire transaction rolls back)
     */
    public function bookMultipleSlots(array $bookings, int $actorUserId): Collection
    {
        return DB::transaction(function () use ($bookings, $actorUserId): Collection {
            $results = new Collection;

            foreach ($bookings as $booking) {
                $results->push(
                    $this->bookSlot($booking['request'], $booking['pick'], $actorUserId),
                );
            }

            return $results;
        });
    }

    /**
     * Compute valid start times for a given makeup request row.
     *
     * @return array<int, CarbonImmutable>
     */
    public function availableStartTimes(ScheduleMakeupRequest $makeupRequest): array
    {
        /** @var User $therapist */
        $therapist = $makeupRequest->therapist;
        $schedule = $makeupRequest->schedule;

        if ($schedule === null) {
            return [];
        }

        $duration = $schedule->durationMinutes();

        $windows = $this->availabilityRepo->windowsForTherapistFromDate($therapist, $makeupRequest->event_date->toDateString());

        if ($windows->isEmpty()) {
            return [];
        }

        $busy = $this->availabilityRepo->busySchedulesForWindows($therapist, $windows);

        $windowPairs = $windows
            ->map(static fn (ScheduleMakeupAvailability $w): array => [$w->startUtc(), $w->endUtc()])
            ->values()
            ->all();

        $busyPairs = $busy
            ->map(static fn (Schedule $s): array => [$s->startUtc(), $s->endUtc()])
            ->values()
            ->all();

        return $this->slotCalculator->validStartTimes($windowPairs, $busyPairs, $duration);
    }
}
