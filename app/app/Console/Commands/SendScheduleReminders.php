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

    protected $description = 'Send email reminders for upcoming schedules (24h and 1h before)';

    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly UserTimezoneService $timezoneService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->send24HourReminders();
        $this->send1HourReminders();

        return self::SUCCESS;
    }

    private function send24HourReminders(): void
    {
        $startWindow = Carbon::now()->addHours(24);
        $endWindow = Carbon::now()->addHours(24)->addMinutes(30);

        $schedules = $this->scheduleRepository->getSchedulesInWindow($startWindow, $endWindow);

        foreach ($schedules as $schedule) {
            $this->sendRemindersForSchedule($schedule, '24h');
        }

        if ($schedules->isNotEmpty()) {
            $this->info("Sent 24h reminders for {$schedules->count()} schedules.");
        }
    }

    private function send1HourReminders(): void
    {
        $startWindow = Carbon::now()->addHour();
        $endWindow = Carbon::now()->addHour()->addMinutes(30);

        $schedules = $this->scheduleRepository->getSchedulesInWindow($startWindow, $endWindow);

        foreach ($schedules as $schedule) {
            $this->sendRemindersForSchedule($schedule, '1h');
        }

        if ($schedules->isNotEmpty()) {
            $this->info("Sent 1h reminders for {$schedules->count()} schedules.");
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

        // Student
        if ($schedule->student && $schedule->student->email) {
            // Use UserTimezoneService to resolve timezone with proper fallback
            $profileTimezone = $schedule->student->studentProfile?->timezone;
            $timezone = $this->resolveUserTimezone($schedule->student, $profileTimezone);

            $recipients[] = [
                'email' => $schedule->student->email,
                'name' => $schedule->student->name,
                'timezone' => $timezone,
            ];
        }

        // Guardian/Parent
        if ($schedule->student && $schedule->student->studentProfile) {
            $profile = $schedule->student->studentProfile;
            // Assume guardian is in same timezone as student
            $studentTimezone = $this->resolveUserTimezone(
                $schedule->student,
                $profile->timezone
            );

            // Check linked parent user
            if ($profile->parent && $profile->parent->email) {
                // Try to get parent's timezone, fallback to student's timezone
                $parentProfileTimezone = $profile->parent->therapistProfile?->timezone
                    ?? $profile->parent->studentProfile?->timezone
                    ?? $profile->parent->parentProfile?->timezone;

                $timezone = $this->resolveUserTimezone(
                    $profile->parent,
                    $parentProfileTimezone ?? $studentTimezone
                );

                $recipients[] = [
                    'email' => $profile->parent->email,
                    'name' => $profile->parent->name,
                    'timezone' => $timezone,
                ];
            }

            // Check manually entered guardian email (use student's timezone)
            if ($profile->parent_guardian_email) {
                $recipients[] = [
                    'email' => $profile->parent_guardian_email,
                    'name' => $profile->parent_guardian_name ?? 'Guardian',
                    'timezone' => $studentTimezone,
                ];
            }
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
