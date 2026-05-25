<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Enums\ScheduleEmailType;
use App\Events\Schedule\EmailSent;
use App\Mail\ScheduleReminderMail;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        $startedAt = Carbon::now();

        $stats48h = $this->send48HourReminders();
        $stats2h = $this->send2HourReminders();

        Log::info('schedule:send-reminders completed', [
            'ran_at' => $startedAt->toIso8601String(),
            '48h' => $stats48h,
            '2h' => $stats2h,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{found: int, sent: int, skipped: int}
     */
    private function send48HourReminders(): array
    {
        $startWindow = Carbon::now()->addHours(48);
        $endWindow = Carbon::now()->addHours(48)->addMinutes(30);

        return $this->processWindow($startWindow, $endWindow, '48h');
    }

    /**
     * @return array{found: int, sent: int, skipped: int}
     */
    private function send2HourReminders(): array
    {
        $startWindow = Carbon::now()->addHours(2);
        $endWindow = Carbon::now()->addHours(2)->addMinutes(30);

        return $this->processWindow($startWindow, $endWindow, '2h');
    }

    /**
     * Process a single reminder window, returning per-run counts so that
     * silent no-op runs (ran, found nothing) are distinguishable in the logs
     * from runs that never executed.
     *
     * @return array{found: int, sent: int, skipped: int}
     */
    private function processWindow(Carbon $startWindow, Carbon $endWindow, string $type): array
    {
        $schedules = $this->scheduleRepository->getSchedulesInWindow($startWindow, $endWindow);

        $sent = 0;
        foreach ($schedules as $schedule) {
            if ($this->sendRemindersForSchedule($schedule, $type)) {
                $sent++;
            }
        }

        $found = $schedules->count();

        if ($found > 0) {
            $this->info("{$type} window: found {$found}, sent {$sent}.");
        }

        return [
            'found' => $found,
            'sent' => $sent,
            'skipped' => $found - $sent,
        ];
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

    /**
     * @return bool True when a reminder email was queued, false when the schedule was skipped.
     */
    private function sendRemindersForSchedule(Schedule $schedule, string $type): bool
    {
        if ($schedule->service && ! $schedule->service->allowsScheduleEmail()) {
            Log::info('schedule:send-reminders skipped schedule', [
                'schedule_id' => $schedule->id,
                'type' => $type,
                'reason' => 'service_disallows_email',
            ]);

            return false;
        }

        // Only notify student schedule contact — therapists do not receive reminder emails
        $profile = $schedule->student?->studentProfile;
        if (! $profile?->schedule_email) {
            Log::info('schedule:send-reminders skipped schedule', [
                'schedule_id' => $schedule->id,
                'type' => $type,
                'reason' => 'no_schedule_email',
            ]);

            return false;
        }

        $studentTimezone = $this->resolveUserTimezone($schedule->student, $profile->timezone);

        $uniqueRecipients = [
            $profile->schedule_email => [
                'email' => $profile->schedule_email,
                'name' => $profile->parent_guardian_name ?? 'Schedule contact',
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
            Event::dispatch(new EmailSent($schedule->id, $emailType, $email));
        }

        return true;
    }
}
