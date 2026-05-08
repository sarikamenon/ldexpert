<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Enums\ScheduleEmailType;
use App\Events\ScheduleEmailSent;
use App\Mail\ScheduleReminderMail;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
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
        if ($schedule->service && ! $schedule->service->allowsScheduleEmail()) {
            return;
        }

        // Only notify student schedule contact — therapists do not receive reminder emails
        $profile = $schedule->student?->studentProfile;
        if (! $profile?->schedule_email) {
            return;
        }

        $studentTimezone = $this->resolveUserTimezone($schedule->student, $profile->timezone);

        $uniqueRecipients = [
            $profile->schedule_email => [
                'email'    => $profile->schedule_email,
                'name'     => $profile->parent_guardian_name ?? 'Schedule contact',
                'timezone' => $studentTimezone,
            ],
        ];

        $emailType = $type === '48h'
            ? ScheduleEmailType::REMINDER_48H
            : ScheduleEmailType::REMINDER_2H;

        foreach ($uniqueRecipients as $recipient) {
            /** @var string $email */
            $email = $recipient['email'];
            Mail::to($email)->queue(
                new ScheduleReminderMail(
                    $schedule,
                    $type,
                    $recipient['name'],
                    $recipient['timezone']
                )
            );
            Event::dispatch(new ScheduleEmailSent($schedule->id, $emailType, $email));
        }
    }
}
