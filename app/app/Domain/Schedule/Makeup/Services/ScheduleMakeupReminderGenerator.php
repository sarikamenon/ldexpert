<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\DTOs\Schedule\Makeup\CreateMakeupRequestDTO;
use App\DTOs\Schedule\Makeup\GenerateMakeupRemindersDTO;
use App\Models\Schedule;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scans school calendar events within a lookahead window and creates pending
 * make-up reminder rows for every in-scope scheduled session that doesn't
 * already have one.
 *
 * Idempotent: the unique (school_calendar_event_id, schedule_id) index plus
 * an in-memory skip-set ensure repeated runs never double-insert.
 */
final class ScheduleMakeupReminderGenerator
{
    public function __construct(
        private readonly ScheduleMakeupRequestRepositoryInterface $repository,
    ) {}

    /**
     * @return array{events_scanned: int, rows_created: int, errors: int}
     */
    public function generate(GenerateMakeupRemindersDTO $dto): array
    {
        $windowEnd = $dto->today->addDays($dto->lookaheadDays);
        $events = $this->repository->listEventsOverlappingWindow($dto->today, $windowEnd);

        $rowsCreated = 0;
        $errors = 0;

        $events->each(function (SchoolCalendarEvent $event) use ($dto, $windowEnd, &$rowsCreated, &$errors): void {
            try {
                $rowsCreated += $this->generateForEvent($event, $dto, $windowEnd);
            } catch (Throwable $e) {
                $errors++;
                report($e);
            }
        });

        return [
            'events_scanned' => $events->count(),
            'rows_created' => $rowsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Expand the event's [start_date, end_date] into the subset of dates
     * inside [today, today + lookahead], then create pending rows for each
     * in-scope schedule on each of those dates.
     */
    private function generateForEvent(
        SchoolCalendarEvent $event,
        GenerateMakeupRemindersDTO $dto,
        CarbonImmutable $windowEnd,
    ): int {
        // Skip events that haven't opted in to makeup requests, or that lack
        // the required dates (legacy rows from before this feature).
        if (! $event->request_makeup || $event->reminder_date === null || $event->response_date === null) {
            return 0;
        }

        $reminderDate = CarbonImmutable::parse($event->reminder_date->toDateString());
        $responseDate = CarbonImmutable::parse($event->response_date->toDateString());

        $skipScheduleIds = $this->repository->existingScheduleIdsForEvent($event);
        $batchIdentifiers = $this->repository->batchIdentifiersForEvent($event);

        $eventStart = CarbonImmutable::parse($event->start_date->toDateString())->startOfDay();
        $eventEnd = CarbonImmutable::parse($event->end_date->toDateString())->startOfDay();

        $scanStart = $eventStart->greaterThan($dto->today) ? $eventStart : $dto->today;
        $scanEnd = $eventEnd->lessThan($windowEnd) ? $eventEnd : $windowEnd;

        if ($scanStart->greaterThan($scanEnd)) {
            return 0;
        }

        $created = 0;

        for ($date = $scanStart; $date->lessThanOrEqualTo($scanEnd); $date = $date->addDay()) {
            $created += $this->generateForEventDate(
                $event,
                $date,
                $reminderDate,
                $responseDate,
                $skipScheduleIds,
                $batchIdentifiers,
            );
        }

        return $created;
    }

    /**
     * @param  Collection<int, int>  $skipScheduleIds  mutated in place as rows are created
     * @param  Collection<string, array{batch_number: string, response_token: string}>  $batchIdentifiers  "studentId:therapistId" => identifiers; mutated as new batches are minted
     */
    private function generateForEventDate(
        SchoolCalendarEvent $event,
        CarbonImmutable $date,
        CarbonImmutable $reminderDate,
        CarbonImmutable $responseDate,
        Collection $skipScheduleIds,
        Collection $batchIdentifiers,
    ): int {
        $schedules = $this->repository->inScopeSchedulesForEventOnDate($event, $date, $skipScheduleIds);

        $created = 0;

        $schedules->each(function (Schedule $schedule) use ($event, $date, $reminderDate, $responseDate, $skipScheduleIds, $batchIdentifiers, &$created): void {
            $therapistId = $schedule->sub_therapist_id ?? $schedule->therapist_id;
            $batchKey = $schedule->student_id.':'.$therapistId;
            $identifiers = $batchIdentifiers->get($batchKey) ?? [
                'batch_number' => 'MR_'.Str::random(29),
                'response_token' => Str::random(64),
            ];

            $createDto = CreateMakeupRequestDTO::fromGeneration(
                event: $event,
                schedule: $schedule,
                eventDate: $date,
                reminderDate: $reminderDate,
                responseDate: $responseDate,
                batchNumber: $identifiers['batch_number'],
                responseToken: $identifiers['response_token'],
            );

            try {
                $this->repository->create($createDto);
                $skipScheduleIds->push($schedule->id);
                $batchIdentifiers->put($batchKey, $identifiers);
                $created++;
            } catch (QueryException $e) {
                // Unique-constraint race with a concurrent worker — expected,
                // not reported. Add to skip-set so we don't retry.
                if ($this->isUniqueConstraintViolation($e)) {
                    $skipScheduleIds->push($schedule->id);

                    return;
                }

                throw $e;
            }
        });

        return $created;
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
