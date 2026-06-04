<?php

declare(strict_types=1);

namespace App\Mail\Schedule;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a private-student school's manager the first time any of its students
 * is scheduled, prompting them to generate & send the first (family) invoice (§9).
 */
class FirstScheduleManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly School $school,
        public readonly string $managerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "First session scheduled for {$this->school->display_name} — generate the first invoice",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedule.first-schedule-manager-notification',
            with: [
                'managerName' => $this->managerName,
                'schoolName' => $this->school->display_name,
            ],
        );
    }
}
