<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class PaymentSessionDTO
{
    public function __construct(
        public string $sessionId,
        public string $paymentUrl,
        public ?string $expiresAt,
        public string $gatewayIdentifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: (string) $data['session_id'],
            paymentUrl: (string) $data['payment_url'],
            expiresAt: $data['expires_at'] ?? null,
            gatewayIdentifier: (string) $data['gateway_identifier'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'payment_url' => $this->paymentUrl,
            'expires_at' => $this->expiresAt,
            'gateway_identifier' => $this->gatewayIdentifier,
        ];
    }
}
