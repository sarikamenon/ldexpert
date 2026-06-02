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
 * Email #4: Sent to therapist when a make-up session is successfully scheduled
 * (Path 1 — parent self-reschedule via availability picker).
 */
class TherapistMakeupScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $therapist,
        public readonly string $studentDisplayName,
        public readonly string $scheduledDateTime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Student {$this->studentDisplayName} | Make-Up Session Scheduled",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.makeup.therapist-makeup-scheduled',
            with: [
                'therapistName' => $this->therapist->name,
                'studentDisplayName' => $this->studentDisplayName,
                'scheduledDateTime' => $this->scheduledDateTime,
            ],
        );
    }
}
