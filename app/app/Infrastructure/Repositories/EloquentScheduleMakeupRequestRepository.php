<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\DTOs\Schedule\Makeup\CreateMakeupRequestDTO;
use App\DTOs\Schedule\Makeup\RecordMakeupResponseDTO;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class EloquentScheduleMakeupRequestRepository implements ScheduleMakeupRequestRepositoryInterface
{
    /**
     * @return Collection<int, int>
     */
    public function existingScheduleIdsForEvent(SchoolCalendarEvent $event): Collection
    {
        return ScheduleMakeupRequest::query()
            ->forEvent($event)
            ->pluck('schedule_id')
            ->map(static fn ($id): int => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, SchoolCalendarEvent>
     */
    public function listEventsOverlappingWindow(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return SchoolCalendarEvent::query()
            ->overlappingDateRange($from->toDateString(), $to->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, int>  $excludeScheduleIds
     * @return Collection<int, Schedule>
     */
    public function inScopeSchedulesForEventOnDate(
        SchoolCalendarEvent $event,
        CarbonInterface $date,
        Collection $excludeScheduleIds,
    ): Collection {
        return Schedule::query()
            ->forSchoolOnDate($event->school_id, $date->toDateString())
            ->scheduled()
            ->whereNotIn('id', $excludeScheduleIds->all())
            ->get();
    }

    public function create(CreateMakeupRequestDTO $dto): ScheduleMakeupRequest
    {
        return ScheduleMakeupRequest::query()->create($dto->toAttributes());
    }

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function findBatchByResponseToken(string $token): Collection
    {
        $batchNumber = ScheduleMakeupRequest::query()
            ->where('response_token', $token)
            ->value('batch_number');

        if ($batchNumber === null) {
            return new Collection;
        }

        return ScheduleMakeupRequest::query()
            ->with(['therapist', 'schedule', 'student.studentProfile'])
            ->forBatch($batchNumber)
            ->unresponded()
            ->get();
    }

    /**
     * @return Collection<string, array{batch_number: string, response_token: string}>
     */
    public function batchIdentifiersForEvent(SchoolCalendarEvent $event): Collection
    {
        return ScheduleMakeupRequest::query()
            ->forEvent($event)
            ->select(['student_id', 'therapist_id', 'batch_number', 'response_token'])
            ->get()
            ->mapWithKeys(static fn (ScheduleMakeupRequest $row): array => [
                $row->student_id.':'.$row->therapist_id => [
                    'batch_number' => $row->batch_number,
                    'response_token' => $row->response_token,
                ],
            ]);
    }

    public function findAndLock(int $id): ScheduleMakeupRequest
    {
        return ScheduleMakeupRequest::query()
            ->lockForUpdate()
            ->findOrFail($id);
    }

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function listDueForReminder(CarbonInterface $on): Collection
    {
        return ScheduleMakeupRequest::query()
            ->dueForReminder($on)
            ->with(['schedule', 'student', 'therapist'])
            ->get();
    }

    /**
     * @return Collection<string, EloquentCollection<int, ScheduleMakeupRequest>>
     */
    public function listPendingDueBatches(CarbonInterface $on): Collection
    {
        return ScheduleMakeupRequest::query()
            ->dueForReminder($on)
            ->with([
                'schedule.ssa',
                'schedule.service',
                'student.studentProfile',
                'therapist.therapistProfile',
                'calendarEvent',
            ])
            ->orderBy('event_date')
            ->get()
            ->groupBy('batch_number');
    }

    public function markBatchSent(string $batchNumber, CarbonInterface $sentAt): int
    {
        return ScheduleMakeupRequest::query()
            ->forBatch($batchNumber)
            ->pending()
            ->update([
                'status' => ScheduleMakeupRequestStatus::SENT->value,
                'reminder_sent_at' => $sentAt->toDateTimeString(),
            ]);
    }

    public function markBatchFailed(string $batchNumber): int
    {
        return ScheduleMakeupRequest::query()
            ->forBatch($batchNumber)
            ->pending()
            ->update([
                'status' => ScheduleMakeupRequestStatus::FAILED->value,
            ]);
    }

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function listOverdueForResponse(CarbonInterface $on): Collection
    {
        return ScheduleMakeupRequest::query()
            ->overdueForResponse($on)
            ->with(['therapist', 'student', 'schedule.school'])
            ->get();
    }

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function listPendingForEvent(SchoolCalendarEvent $event): Collection
    {
        return ScheduleMakeupRequest::query()
            ->forEvent($event)
            ->pending()
            ->get();
    }

    public function countForTherapist(User $therapist, ?ScheduleMakeupRequestStatus $status = null): int
    {
        return ScheduleMakeupRequest::query()
            ->forTherapist($therapist)
            ->when($status, static fn ($query, ScheduleMakeupRequestStatus $s) => $query->withStatus($s))
            ->count();
    }

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    public function pageForTherapist(
        User $therapist,
        ?ScheduleMakeupRequestStatus $status,
        int $offset,
        int $limit,
    ): Collection {
        /** @var EloquentCollection<int, ScheduleMakeupRequest> $rows */
        $rows = ScheduleMakeupRequest::query()
            ->forTherapist($therapist)
            ->when($status, static fn ($query, ScheduleMakeupRequestStatus $s) => $query->withStatus($s))
            ->with([
                'schedule.service',
                'schedule.school',
                'student',
                'calendarEvent',
                'respondedBy',
                'makeupSchedule',
            ])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        return Collection::make($rows->all());
    }

    public function recordResponse(ScheduleMakeupRequest $request, RecordMakeupResponseDTO $dto): ScheduleMakeupRequest
    {
        $request->fill($dto->toAttributes());
        $request->save();

        return $request;
    }

    public function bulkAutoDecline(CarbonInterface $on): int
    {
        return ScheduleMakeupRequest::query()
            ->overdueForResponse($on)
            ->update([
                'status' => ScheduleMakeupRequestStatus::DECLINED->value,
                'responded_by_type' => ScheduleMakeupRespondedByType::SYSTEM->value,
                'response_source' => ScheduleMakeupResponseSource::AUTO_DECLINED->value,
                'responded_by_user_id' => null,
                'responded_at' => $on->toDateTimeString(),
            ]);
    }

    public function linkBookedSchedule(ScheduleMakeupRequest $request, int $scheduleId): ScheduleMakeupRequest
    {
        $request->fill([
            'makeup_schedule_id' => $scheduleId,
            'status' => ScheduleMakeupRequestStatus::SCHEDULED,
        ]);
        $request->save();

        return $request;
    }

    public function markNotRequired(ScheduleMakeupRequest $request, string $reason): ScheduleMakeupRequest
    {
        $request->fill([
            'status' => ScheduleMakeupRequestStatus::NOT_REQUIRED,
            'reason' => $reason,
        ]);
        $request->save();

        return $request;
    }
}
