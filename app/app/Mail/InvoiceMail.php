<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?string $customMessage = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} - {$this->invoice->school_display_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'customMessage' => $this->customMessage,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(\App\Domain\Invoice\Services\InvoicePdfService::class)
            ->generatePdf($this->invoice);

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn() => $pdf->output(),
                "invoice-{$this->invoice->invoice_number}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
