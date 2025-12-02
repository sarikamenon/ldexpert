<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Mail\ScheduleReminderMail;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendScheduleReminders extends Command
{
    protected $signature = 'schedule:send-reminders';

    protected $description = 'Send email reminders for upcoming schedules (24h and 1h before)';

    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository
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

    private function sendRemindersForSchedule(Schedule $schedule, string $type): void
    {
        $recipients = [];

        // Therapist
        if ($schedule->therapist && $schedule->therapist->email) {
            $timezone = $schedule->therapist->therapistProfile?->timezone ?? 'UTC';
            $recipients[] = [
                'email' => $schedule->therapist->email,
                'name' => $schedule->therapist->name,
                'timezone' => $timezone,
            ];
        }

        // Student
        if ($schedule->student && $schedule->student->email) {
            $timezone = $schedule->student->studentProfile?->timezone ?? 'UTC';
            $recipients[] = [
                'email' => $schedule->student->email,
                'name' => $schedule->student->name,
                'timezone' => $timezone,
            ];
        }

        // Guardian/Parent
        if ($schedule->student && $schedule->student->studentProfile) {
            $profile = $schedule->student->studentProfile;
            $timezone = $profile->timezone ?? 'UTC'; // Assume guardian is in same timezone as student

            // Check linked parent user
            if ($profile->parent && $profile->parent->email) {
                $recipients[] = [
                    'email' => $profile->parent->email,
                    'name' => $profile->parent->name,
                    'timezone' => $timezone,
                ];
            }

            // Check manually entered guardian email
            if ($profile->parent_guardian_email) {
                $recipients[] = [
                    'email' => $profile->parent_guardian_email,
                    'name' => $profile->parent_guardian_name ?? 'Guardian',
                    'timezone' => $timezone,
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
