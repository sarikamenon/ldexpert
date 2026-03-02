<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadFollowUpReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $recipientName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Lead Follow-Up Reminder: {$this->lead->full_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-follow-up-reminder',
            with: [
                'lead' => $this->lead,
                'recipientName' => $this->recipientName,
                'leadUrl' => route('admin.leads.show', $this->lead),
            ],
        );
    }
}
