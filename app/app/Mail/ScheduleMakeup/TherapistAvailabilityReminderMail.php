<?php

declare(strict_types=1);

namespace App\Mail\ScheduleMakeup;

use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email #1: Sent to therapist when they have not added availability windows
 * for an upcoming school closure that requires make-up sessions.
 * Fires configurable days before reminder_date.
 */
class TherapistAvailabilityReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $therapist,
        public readonly SchoolCalendarEvent $calendarEvent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Enter Available Make-Up Session Times into NOVA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.makeup.therapist-availability-reminder',
            with: [
                'therapistName' => $this->therapist->name,
                'eventTitle' => $this->calendarEvent->title,
                'eventDate' => $this->calendarEvent->start_date->format((string) config('display.date_long')),
            ],
        );
    }
}
