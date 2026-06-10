<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceOverdueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $daysOverdue,
        public readonly ?string $paymentUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('invoice.from_address'),
                config('invoice.from_name'),
            ),
            subject: "Overdue Payment — {$this->daysOverdue} Days Past Due",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-overdue',
            with: [
                'daysOverdue' => $this->daysOverdue,
                'paymentUrl' => $this->paymentUrl,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
