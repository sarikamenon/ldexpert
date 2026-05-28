<?php

declare(strict_types=1);

namespace App\Mail\ScheduleMakeup;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email #5: Sent to therapist when no make-up is accepted before the response
 * deadline (auto-decline). Only sent for non-private students.
 */
class TherapistNonAcceptedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $therapist,
        public readonly string $studentDisplayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Student {$this->studentDisplayName} | Enter Non-Accepted Make-Up in RSM",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.makeup.therapist-non-accepted-notification',
            with: [
                'therapistName' => $this->therapist->name,
                'studentDisplayName' => $this->studentDisplayName,
            ],
        );
    }
}
