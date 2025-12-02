<?php

declare(strict_types=1);

namespace App\Mail;

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
        $date = $this->schedule->schedule_date->format('M d, Y');

        return new Envelope(
            subject: "NOVA - {$action}: {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedule-notification',
        );
    }
}

