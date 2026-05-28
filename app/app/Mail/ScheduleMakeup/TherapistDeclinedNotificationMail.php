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
 * Email #3: Sent to therapist when a parent/student declines a make-up session.
 * Only sent for non-private students (school.is_private_student = false).
 */
class TherapistDeclinedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $therapist,
        public readonly string $studentDisplayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Student {$this->studentDisplayName} | Enter Declined Make-Up Session in RSM",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.makeup.therapist-declined-notification',
            with: [
                'therapistName' => $this->therapist->name,
                'studentDisplayName' => $this->studentDisplayName,
            ],
        );
    }
}
