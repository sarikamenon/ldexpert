<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SessionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionLogSentBackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SessionLog $sessionLog
    ) {
        $this->sessionLog->loadMissing(['therapist', 'therapist.therapistProfile', 'student', 'service']);
    }

    public function envelope(): Envelope
    {
        $student = $this->sessionLog->student->name ?? 'Unknown student';
        $tz = $this->sessionLog->displayTimezone();
        $date = $this->sessionLog->localDate($tz)->format('M d, Y');

        return new Envelope(
            subject: "Session log sent back for rectification – {$student}, {$date}",
        );
    }

    public function content(): Content
    {
        $tz = $this->sessionLog->displayTimezone();

        return new Content(
            view: 'emails.session-log-sent-back',
            with: [
                'sessionDateFormatted' => $this->sessionLog->localDate($tz)->format('M d, Y'),
            ],
        );
    }
}
