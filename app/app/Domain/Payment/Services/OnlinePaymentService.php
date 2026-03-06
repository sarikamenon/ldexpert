<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Finance\Services\InvoicePaymentService;
use App\DTOs\CreatePaymentSessionDTO;
use App\DTOs\PaymentResultDTO;
use App\DTOs\PaymentSessionDTO;
use App\DTOs\RecordInvoicePaymentDTO;
use App\Enums\PaymentGatewayLogDirection;
use App\Enums\PaymentGatewayTransactionStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentGatewayTransaction;
use Illuminate\Support\Facades\DB;

final class OnlinePaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly InvoicePaymentService $paymentService,
    ) {}

    public function createCheckoutSession(Invoice $invoice, string $customerEmail): PaymentSessionDTO
    {
        $gateway = $this->gatewayManager->gateway();
        $token = $invoice->payment_token;

        $dto = new CreatePaymentSessionDTO(
            invoiceId: $invoice->id,
            amount: (float) $invoice->total,
            currency: (string) config('payment.currency', 'usd'),
            customerEmail: $customerEmail,
            successUrl: route('payment.success', ['session_id' => '{CHECKOUT_SESSION_ID}']),
            cancelUrl: route('payment.cancel', ['token' => $token]),
            metadata: [
                'invoice_id' => (string) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'school_id' => (string) $invoice->school_id,
            ],
        );

        $transaction = PaymentGatewayTransaction::create([
            'invoice_id' => $invoice->id,
            'gateway' => $gateway->getIdentifier(),
            'gateway_session_id' => 'pending_'.uniqid(),
            'payment_url' => '',
            'status' => PaymentGatewayTransactionStatus::PENDING->value,
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'expires_at' => config('payment.link_expiry_hours') > 0
                ? now()->addHours((int) config('payment.link_expiry_hours'))
                : null,
        ]);

        $transaction->addLog(
            'session_create_request',
            PaymentGatewayLogDirection::OUTGOING->value,
            $dto->toArray()
        );

        $session = $gateway->createPaymentSession($invoice, $dto);

        $transaction->update([
            'gateway_session_id' => $session->sessionId,
            'payment_url' => $session->paymentUrl,
            'expires_at' => $session->expiresAt,
        ]);

        $transaction->addLog(
            'session_create_response',
            PaymentGatewayLogDirection::INCOMING->value,
            $session->toArray()
        );

        return $session;
    }

    public function processPaymentCompletion(string $sessionId): InvoicePayment
    {
        return DB::transaction(function () use ($sessionId): InvoicePayment {
            $transaction = PaymentGatewayTransaction::where('gateway_session_id', $sessionId)->firstOrFail();

            if ($transaction->isCompleted()) {
                /** @var InvoicePayment $existingPayment */
                $existingPayment = InvoicePayment::where('payment_gateway_transaction_id', $transaction->id)->firstOrFail();

                return $existingPayment;
            }

            $gateway = $this->gatewayManager->gateway($transaction->gateway->value);

            $transaction->addLog(
                'payment_verification_request',
                PaymentGatewayLogDirection::OUTGOING->value,
                ['session_id' => $sessionId]
            );

            $result = $gateway->retrievePaymentDetails($sessionId);

            $transaction->addLog(
                'payment_verification_response',
                PaymentGatewayLogDirection::INCOMING->value,
                $result->toArray()
            );

            return $this->recordGatewayPayment($transaction, $result);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function processWebhook(array $payload, string $signature): ?InvoicePayment
    {
        $gateway = $this->gatewayManager->gateway();

        $result = $gateway->handleWebhook($payload, $signature);

        if (! $result) {
            return null;
        }

        $transaction = PaymentGatewayTransaction::where('gateway_session_id', $result->sessionId)->first();

        if (! $transaction) {
            return null;
        }

        $transaction->addLog(
            'webhook_received',
            PaymentGatewayLogDirection::INCOMING->value,
            $payload,
            $payload['id'] ?? null
        );

        if ($transaction->isCompleted()) {
            $transaction->addLog(
                'webhook_acknowledged',
                PaymentGatewayLogDirection::OUTGOING->value,
                ['status' => 'already_completed']
            );

            return null;
        }

        return DB::transaction(function () use ($transaction, $result): InvoicePayment {
            $payment = $this->recordGatewayPayment($transaction, $result);

            $transaction->addLog(
                'webhook_acknowledged',
                PaymentGatewayLogDirection::OUTGOING->value,
                ['status' => 'payment_recorded', 'payment_id' => $payment->id]
            );

            return $payment;
        });
    }

    private function recordGatewayPayment(
        PaymentGatewayTransaction $transaction,
        PaymentResultDTO $result
    ): InvoicePayment {
        /** @var Invoice $invoice */
        $invoice = $transaction->invoice;

        $dto = new RecordInvoicePaymentDTO(
            invoiceId: $invoice->id,
            paidAt: $result->paidAt,
            amount: $result->amountPaid,
            method: $result->paymentMethod,
            reference: $result->gatewayTransactionId,
            notes: "Online payment via {$transaction->gateway->label()}",
            recordedById: null,
            schoolId: $invoice->school_id,
        );

        $payment = $this->paymentService->recordPayment($dto);

        $payment->update([
            'gateway' => $transaction->gateway->value,
            'gateway_transaction_id' => $result->gatewayTransactionId,
            'payment_gateway_transaction_id' => $transaction->id,
        ]);

        $transaction->update([
            'status' => PaymentGatewayTransactionStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        if ($invoice->isFullyPaid()) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return $payment->refresh();
    }
}
