<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\Domain\Schedule\Makeup\Services\TherapistMakeupNotificationService;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Email #1: Remind therapists who have pending/sent makeup requests for upcoming
 * closures but have not entered any availability windows for the affected dates.
 *
 * Fires `therapist_availability_reminder_offset_days` (default 3) days before
 * the parent reminder_date, giving therapists time to add availability before
 * families are notified.
 */
final class MakeupTherapistAvailabilityReminder extends Command
{
    protected $signature = 'makeup-reminders:therapist-availability';

    protected $description = 'Email therapists who have not entered make-up availability for upcoming closures.';

    public function handle(
        ScheduleMakeupRequestRepositoryInterface $requestRepo,
        ScheduleMakeupAvailabilityRepositoryInterface $availabilityRepo,
        TherapistMakeupNotificationService $notificationService,
    ): int {
        $today = CarbonImmutable::today();
        $offsetDays = (int) config('schedule_makeup.therapist_availability_reminder_offset_days', 3);

        $targetDate = $today->addDays($offsetDays);

        $events = $requestRepo->listEventsOverlappingWindow($today, $targetDate->addDays(1));

        $sent = 0;

        $events->each(function (SchoolCalendarEvent $event) use ($availabilityRepo, $notificationService, $today, $offsetDays, &$sent): void {
            if ($event->reminder_date === null) {
                return;
            }

            $reminderDate = CarbonImmutable::parse($event->reminder_date->toDateString());
            $sendDate = $reminderDate->subDays($offsetDays);

            // Fire on exactly one day. The command runs daily and the reminder is a
            // single nudge; gating on the whole [sendDate, reminderDate] window would
            // re-send every day (no email-log row is written to de-dupe).
            if (! $today->isSameDay($sendDate)) {
                return;
            }

            // A make-up may be booked on any availability from the closure's
            // start date onward, so the earliest relevant date is the event start.
            $earliestEventDate = $event->start_date->toDateString();

            /** @var Collection<int, ScheduleMakeupRequest> $requests */
            $requests = ScheduleMakeupRequest::query()
                ->forEvent($event)
                ->unresponded()
                ->with('therapist')
                ->get();

            $therapistIds = $requests->pluck('therapist_id')->unique();

            $therapistIds->each(function (int $therapistId) use ($event, $earliestEventDate, $availabilityRepo, $notificationService, &$sent): void {
                /** @var User|null $therapist */
                $therapist = User::find($therapistId);
                if ($therapist === null) {
                    return;
                }

                if ($availabilityRepo->therapistHasAvailabilityFromDate($therapist, $earliestEventDate)) {
                    return;
                }

                $notificationService->sendAvailabilityReminder($therapist, $event);
                $sent++;
            });
        });

        $this->info(sprintf('Sent %d therapist availability reminder(s).', $sent));

        return self::SUCCESS;
    }
}
