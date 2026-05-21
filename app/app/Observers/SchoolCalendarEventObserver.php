<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;

/**
 * Keeps pending make-up reminder rows in sync with their parent closure event.
 *
 * Only `deleted` is handled. On delete, pending rows for the event are
 * soft-deleted — the next daily MakeupRemindersGenerate run rebuilds whatever
 * is still valid. Non-pending rows are deliberately left untouched: they
 * represent real-world side effects (emails dispatched, parent responses
 * recorded) that should not silently disappear because the underlying
 * calendar event was removed.
 *
 * TODO: edit handling for SchoolCalendarEvent updates is not implemented yet.
 * When an admin changes start_date / end_date / event_type / school_id,
 * existing pending rows may no longer match the new event shape. Confirm with
 * client whether updates should refresh pending rows automatically, require
 * delete + recreate, or surface a warning UI.
 */
final class SchoolCalendarEventObserver
{
    public function __construct(
        private readonly ScheduleMakeupRequestRepositoryInterface $repository,
    ) {}

    public function deleted(SchoolCalendarEvent $event): void
    {
        if ($event->isForceDeleting()) {
            return;
        }

        $this->repository
            ->listPendingForEvent($event)
            ->each(static fn (ScheduleMakeupRequest $row) => $row->delete());
    }
}
