<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Time\UserTimezoneService;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduleReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Schedule $schedule,
        public readonly string $type, // '48h' or '2h'
        public readonly string $recipientName,
        public readonly string $timezone
    ) {}

    public function envelope(): Envelope
    {
        /** @var \App\Models\User $therapist */
        $therapist = $this->schedule->therapist;
        $therapistName = $therapist->name;
        $timeUntil = match ($this->type) {
            '48h' => '48 hours',
            '2h' => '2 hours',
            default => 'upcoming',
        };

        return new Envelope(
            subject: "Reminder: Upcoming Session with {$therapistName} in {$timeUntil}",
        );
    }

    public function content(): Content
    {
        /** @var UserTimezoneService $timezoneService */
        $timezoneService = app(UserTimezoneService::class);

        // Create Carbon instances with the schedule's date and time, assuming UTC storage
        $startUtc = $this->schedule->schedule_date->copy()->setTime(
            $this->schedule->start_time->hour,
            $this->schedule->start_time->minute,
            0
        );

        $endUtc = $this->schedule->schedule_date->copy()->setTime(
            $this->schedule->end_time->hour,
            $this->schedule->end_time->minute,
            0
        );

        // Convert to recipient's timezone using the service
        $startLocal = $timezoneService->toUserTimezone($startUtc, overrideTz: $this->timezone);
        $endLocal = $timezoneService->toUserTimezone($endUtc, overrideTz: $this->timezone);

        return new Content(
            view: 'emails.schedule-reminder',
            with: [
                'scheduleDate' => $startLocal->format('M d, Y'),
                'startTime' => $startLocal->format(config('display.time')),
                'endTime' => $endLocal->format(config('display.time')),
                'recipientName' => $this->recipientName,
                'timezone' => $this->timezone,
            ],
        );
    }
}
