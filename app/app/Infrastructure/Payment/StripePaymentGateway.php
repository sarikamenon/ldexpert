<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\DTOs\CreatePaymentSessionDTO;
use App\DTOs\PaymentResultDTO;
use App\DTOs\PaymentSessionDTO;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripePaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
        Stripe::setApiKey((string) ($this->config['secret_key'] ?? ''));
    }

    public function createPaymentSession(Invoice $invoice, CreatePaymentSessionDTO $dto): PaymentSessionDTO
    {
        try {
            $expiryHours = (int) config('payment.link_expiry_hours', 24);
            // Stripe requires expires_at to be between 30 minutes and 24 hours
            $expirySeconds = $expiryHours > 0
                ? min($expiryHours * 3600, 86400)
                : 86400; // Default to 24 hours if 0

            /** @var array<string, mixed> $sessionParams */
            $sessionParams = [
                'payment_method_types' => ['card', 'us_bank_account'],
                'mode' => 'payment',
                'customer_email' => $dto->customerEmail,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $dto->currency,
                            'product_data' => [
                                'name' => "Invoice {$invoice->invoice_number}",
                                'description' => "Payment for invoice {$invoice->invoice_number} - {$invoice->school_display_name}",
                            ],
                            'unit_amount' => (int) round($dto->amount * 100),
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $dto->successUrl,
                'cancel_url' => $dto->cancelUrl,
                'metadata' => $dto->metadata,
                'expires_at' => time() + $expirySeconds,
            ];

            if ($dto->afterExpirationUrl) {
                // Stripe shows a "Get a new payment link" button on expired sessions
                // which auto-creates a new checkout session for the same payment
                $sessionParams['after_expiration'] = [
                    'recovery' => [
                        'enabled' => true,
                        'allow_promotion_codes' => false,
                    ],
                ];
            }

            $session = Session::create($sessionParams);

            return new PaymentSessionDTO(
                sessionId: $session->id,
                paymentUrl: (string) $session->url,
                expiresAt: $session->expires_at ? date('Y-m-d H:i:s', $session->expires_at) : null,
                gatewayIdentifier: $this->getIdentifier(),
            );
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Stripe session creation failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function retrievePaymentDetails(string $sessionId): PaymentResultDTO
    {
        try {
            $session = Session::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent.payment_method'],
            ]);

            /** @var PaymentIntent|null $paymentIntent */
            $paymentIntent = $session->payment_intent;
            $paymentMethod = $this->mapPaymentMethod($paymentIntent);

            return new PaymentResultDTO(
                sessionId: $session->id,
                gatewayTransactionId: $paymentIntent ? $paymentIntent->id : $session->id,
                amountPaid: ($session->amount_total ?? 0) / 100,
                currency: $session->currency ?? (string) config('payment.currency', 'usd'),
                paymentMethod: $paymentMethod,
                paidAt: date('Y-m-d H:i:s'),
                customerEmail: $session->customer_email,
                metadata: $session->metadata?->toArray() ?? [],
            );
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Stripe payment retrieval failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, string $signature): ?PaymentResultDTO
    {
        $webhookSecret = (string) ($this->config['webhook_secret'] ?? '');
        $rawPayload = (string) json_encode($payload);

        try {
            $event = Webhook::constructEvent($rawPayload, $signature, $webhookSecret);
        } catch (\Exception $e) {
            throw new \RuntimeException("Webhook signature verification failed: {$e->getMessage()}", 0, $e);
        }

        if ($event->type !== 'checkout.session.completed') {
            return null;
        }

        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return null;
        }

        return new PaymentResultDTO(
            sessionId: $session->id,
            gatewayTransactionId: is_string($session->payment_intent) ? $session->payment_intent : $session->id,
            amountPaid: ($session->amount_total ?? 0) / 100,
            currency: $session->currency ?? (string) config('payment.currency', 'usd'),
            paymentMethod: PaymentMethod::CREDIT_CARD,
            paidAt: date('Y-m-d H:i:s'),
            customerEmail: $session->customer_email,
            metadata: $session->metadata?->toArray() ?? [],
        );
    }

    public function getIdentifier(): string
    {
        return 'stripe';
    }

    private function mapPaymentMethod(?PaymentIntent $paymentIntent): PaymentMethod
    {
        if (! $paymentIntent || ! $paymentIntent->payment_method) {
            return PaymentMethod::OTHER;
        }

        $pm = $paymentIntent->payment_method;

        if (is_string($pm)) {
            return PaymentMethod::OTHER;
        }

        $type = $pm->type ?? '';

        return match ($type) {
            'card' => PaymentMethod::CREDIT_CARD,
            'us_bank_account', 'ach_debit' => PaymentMethod::ACH,
            'sepa_debit', 'bacs_debit' => PaymentMethod::BANK_TRANSFER,
            default => PaymentMethod::OTHER,
        };
    }
}
