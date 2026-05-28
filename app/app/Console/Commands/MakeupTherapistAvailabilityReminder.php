<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\Domain\Schedule\Makeup\Services\TherapistMakeupNotificationService;
use App\Enums\ScheduleMakeupRequestStatus;
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

            if ($today->lessThan($sendDate) || $today->greaterThan($reminderDate)) {
                return;
            }

            $eventDates = $this->expandEventDates($event);

            /** @var Collection<int, ScheduleMakeupRequest> $requests */
            $requests = ScheduleMakeupRequest::query()
                ->where('school_calendar_event_id', $event->id)
                ->whereIn('status', [
                    ScheduleMakeupRequestStatus::PENDING->value,
                    ScheduleMakeupRequestStatus::SENT->value,
                ])
                ->with('therapist')
                ->get();

            $therapistIds = $requests->pluck('therapist_id')->unique();

            $therapistIds->each(function (int $therapistId) use ($event, $eventDates, $availabilityRepo, $notificationService, &$sent): void {
                /** @var User|null $therapist */
                $therapist = User::find($therapistId);
                if ($therapist === null) {
                    return;
                }

                if ($availabilityRepo->therapistHasAvailabilityForDates($therapist, $eventDates)) {
                    return;
                }

                $notificationService->sendAvailabilityReminder($therapist, $event);
                $sent++;
            });
        });

        $this->info(sprintf('Sent %d therapist availability reminder(s).', $sent));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function expandEventDates(SchoolCalendarEvent $event): array
    {
        $dates = [];
        $start = CarbonImmutable::parse($event->start_date->toDateString());
        $end = CarbonImmutable::parse(($event->end_date ?? $event->start_date)->toDateString());

        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}
