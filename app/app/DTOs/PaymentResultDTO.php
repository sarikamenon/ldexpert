<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PaymentMethod;

final readonly class PaymentResultDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sessionId,
        public string $gatewayTransactionId,
        public float $amountPaid,
        public string $currency,
        public PaymentMethod $paymentMethod,
        public string $paidAt,
        public ?string $customerEmail,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: (string) $data['session_id'],
            gatewayTransactionId: (string) $data['gateway_transaction_id'],
            amountPaid: (float) $data['amount_paid'],
            currency: (string) $data['currency'],
            paymentMethod: $data['payment_method'] instanceof PaymentMethod
                ? $data['payment_method']
                : PaymentMethod::from($data['payment_method']),
            paidAt: (string) $data['paid_at'],
            customerEmail: $data['customer_email'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'amount_paid' => $this->amountPaid,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod->value,
            'paid_at' => $this->paidAt,
            'customer_email' => $this->customerEmail,
            'metadata' => $this->metadata,
        ];
    }
}
