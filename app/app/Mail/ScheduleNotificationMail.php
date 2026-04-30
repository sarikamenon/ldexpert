<?php

declare(strict_types=1);

namespace App\Mail;

use App\Constants\UsTimezones;
use App\Domain\Time\UserTimezoneService;
use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduleNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Schedule $schedule,
        public readonly string $type, // 'created' or 'updated'
        public readonly bool $isRecipientStudent = false
    ) {}

    public function envelope(): Envelope
    {
        $action = $this->type === 'created' ? 'New Schedule' : 'Schedule Update';
        $date = $this->schedule->localStart($this->resolveRecipientTimezone())->format('M d, Y');

        return new Envelope(
            subject: "NOVA - {$action}: {$date}",
        );
    }

    public function content(): Content
    {
        $tz = $this->resolveRecipientTimezone();
        $localStart = $this->schedule->localStart($tz);
        $localEnd = $this->schedule->localEnd($tz);

        return new Content(
            view: 'emails.schedule-notification',
            with: [
                'scheduleDateLong' => $localStart->format('l, F j, Y'),
                'scheduleStartTime' => $localStart->format('g:i A'),
                'scheduleEndTime' => $localEnd->format('g:i A'),
                'scheduleTimezone' => UsTimezones::getTimezoneLabel($tz),
            ],
        );
    }

    private function resolveRecipientTimezone(): string
    {
        if (! $this->isRecipientStudent) {
            return $this->schedule->displayTimezone();
        }

        return app(UserTimezoneService::class)->resolveTimezone($this->schedule->student);
    }
}
