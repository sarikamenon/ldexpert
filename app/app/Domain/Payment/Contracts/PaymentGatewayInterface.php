<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\DTOs\CreatePaymentSessionDTO;
use App\DTOs\PaymentResultDTO;
use App\DTOs\PaymentSessionDTO;
use App\Models\Invoice;

interface PaymentGatewayInterface
{
    /**
     * Create a payment session/checkout for an invoice.
     */
    public function createPaymentSession(Invoice $invoice, CreatePaymentSessionDTO $dto): PaymentSessionDTO;

    /**
     * Verify and retrieve payment details from a completed session.
     */
    public function retrievePaymentDetails(string $sessionId): PaymentResultDTO;

    /**
     * Handle incoming webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, string $signature): ?PaymentResultDTO;

    /**
     * Get the gateway identifier (e.g., 'stripe', 'paypal').
     */
    public function getIdentifier(): string;
}
