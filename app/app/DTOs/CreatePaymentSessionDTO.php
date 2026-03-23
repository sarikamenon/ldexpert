<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class CreatePaymentSessionDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $invoiceId,
        public float $amount,
        public string $currency,
        public string $customerEmail,
        public string $successUrl,
        public string $cancelUrl,
        public array $metadata = [],
        public ?string $afterExpirationUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceId: (int) $data['invoice_id'],
            amount: (float) $data['amount'],
            currency: (string) $data['currency'],
            customerEmail: (string) $data['customer_email'],
            successUrl: (string) $data['success_url'],
            cancelUrl: (string) $data['cancel_url'],
            metadata: $data['metadata'] ?? [],
            afterExpirationUrl: $data['after_expiration_url'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_email' => $this->customerEmail,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'metadata' => $this->metadata,
            'after_expiration_url' => $this->afterExpirationUrl,
        ];
    }
}
