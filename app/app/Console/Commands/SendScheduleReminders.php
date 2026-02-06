<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Time\UserTimezoneService;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Mail\ScheduleReminderMail;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendScheduleReminders extends Command
{
    protected $signature = 'schedule:send-reminders';

    protected $description = 'Send email reminders for upcoming schedules (48h and 2h before)';

    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly UserTimezoneService $timezoneService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->send48HourReminders();
        $this->send2HourReminders();

        return self::SUCCESS;
    }

    private function send48HourReminders(): void
    {
        $startWindow = Carbon::now()->addHours(48);
        $endWindow = Carbon::now()->addHours(48)->addMinutes(30);

        $schedules = $this->scheduleRepository->getSchedulesInWindow($startWindow, $endWindow);

        foreach ($schedules as $schedule) {
            $this->sendRemindersForSchedule($schedule, '48h');
        }

        if ($schedules->isNotEmpty()) {
            $this->info("Sent 48h reminders for {$schedules->count()} schedules.");
        }
    }

    private function send2HourReminders(): void
    {
        $startWindow = Carbon::now()->addHours(2);
        $endWindow = Carbon::now()->addHours(2)->addMinutes(30);

        $schedules = $this->scheduleRepository->getSchedulesInWindow($startWindow, $endWindow);

        foreach ($schedules as $schedule) {
            $this->sendRemindersForSchedule($schedule, '2h');
        }

        if ($schedules->isNotEmpty()) {
            $this->info("Sent 2h reminders for {$schedules->count()} schedules.");
        }
    }

    /**
     * Resolve timezone for a user using UserTimezoneService.
     * Checks profile timezone first, then user timezone, then defaults to UTC.
     */
    private function resolveUserTimezone(?User $user, ?string $profileTimezone = null): string
    {
        return $this->timezoneService->toUserTimezone(
            Carbon::now('UTC'),
            $user,
            $profileTimezone
        )->timezoneName;
    }

    private function sendRemindersForSchedule(Schedule $schedule, string $type): void
    {
        $recipients = [];

        // Therapist
        if ($schedule->therapist && $schedule->therapist->email) {
            // Use UserTimezoneService to resolve timezone with proper fallback:
            // 1. Profile timezone (overrideTz)
            // 2. User timezone
            // 3. Default UTC
            $profileTimezone = $schedule->therapist->therapistProfile?->timezone;
            $timezone = $this->resolveUserTimezone($schedule->therapist, $profileTimezone);

            $recipients[] = [
                'email' => $schedule->therapist->email,
                'name' => $schedule->therapist->name,
                'timezone' => $timezone,
            ];
        }

        // Student side: only schedule_email (no student user email or parent/guardian emails)
        if ($schedule->student?->studentProfile?->schedule_email) {
            $profile = $schedule->student->studentProfile;
            $studentTimezone = $this->resolveUserTimezone(
                $schedule->student,
                $profile->timezone
            );
            $recipients[] = [
                'email' => $profile->schedule_email,
                'name' => $profile->parent_guardian_name ?? 'Schedule contact',
                'timezone' => $studentTimezone,
            ];
        }

        // Unique recipients by email to avoid duplicates
        $uniqueRecipients = [];
        foreach ($recipients as $recipient) {
            if (!isset($uniqueRecipients[$recipient['email']])) {
                $uniqueRecipients[$recipient['email']] = $recipient;
            }
        }

        foreach ($uniqueRecipients as $recipient) {
            Mail::to($recipient['email'])->queue(
                new ScheduleReminderMail(
                    $schedule,
                    $type,
                    $recipient['name'],
                    $recipient['timezone']
                )
            );
        }
    }
}
