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

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?string $customMessage = null,
        public readonly ?string $paymentUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $dateRange = $this->invoice->billing_period_start->format(config('display.date_short_month')).' - '
            .$this->invoice->billing_period_end->format(config('display.date_short_month'));

        return new Envelope(
            from: new Address(
                config('invoice.from_address'),
                config('invoice.from_name'),
            ),
            subject: "Invoice - {$dateRange}",
        );
    }

    public function content(): Content
    {
        $this->invoice->loadMissing(['school', 'sessionLogs', 'lineItems']);

        return new Content(
            view: 'emails.invoice',
            with: [
                'customMessage' => $this->customMessage,
                'paymentUrl' => $this->paymentUrl,
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
                fn () => $pdf->output(),
                "invoice-{$this->invoice->invoice_number}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
