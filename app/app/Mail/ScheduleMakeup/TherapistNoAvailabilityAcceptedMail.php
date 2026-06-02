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
 * Email #2: Sent to therapist when a parent/student accepts a make-up request
 * but the therapist has not added availability windows (Path 2).
 */
class TherapistNoAvailabilityAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $therapist,
        public readonly string $studentDisplayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Schedule Make-Up Session for {$this->studentDisplayName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.makeup.therapist-no-availability-accepted',
            with: [
                'therapistName' => $this->therapist->name,
                'studentDisplayName' => $this->studentDisplayName,
            ],
        );
    }
}
