<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\Services\PaymentGatewayManager;
use App\DTOs\PaymentResultDTO;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayTransactionStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\PaymentGatewayTransaction;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    private PaymentGatewayTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::factory()->create();

        $this->invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'status' => InvoiceStatus::SENT,
            'total' => 1000.00,
            'payment_token' => 'webhook-test-token',
        ]);

        $this->transaction = PaymentGatewayTransaction::create([
            'invoice_id' => $this->invoice->id,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_webhook_test',
            'payment_url' => 'https://checkout.stripe.com/pay/cs_webhook_test',
            'status' => PaymentGatewayTransactionStatus::PENDING->value,
            'amount' => 1000.00,
            'currency' => 'usd',
        ]);
    }

    public function test_webhook_processes_completed_payment(): void
    {
        $resultDTO = new PaymentResultDTO(
            sessionId: 'cs_webhook_test',
            gatewayTransactionId: 'pi_webhook_test',
            amountPaid: 1000.00,
            currency: 'usd',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            paidAt: now()->toDateTimeString(),
            customerEmail: 'billing@school.com',
        );

        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('handleWebhook')->once()->andReturn($resultDTO);

        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_test_webhook',
            'type' => 'checkout.session.completed',
        ], [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $this->invoice->id,
            'amount' => 1000.00,
            'gateway' => 'stripe',
        ]);

        $this->transaction->refresh();
        $this->assertEquals(PaymentGatewayTransactionStatus::COMPLETED, $this->transaction->status);

        $this->invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $this->invoice->status);
    }

    public function test_webhook_is_idempotent_for_completed_transaction(): void
    {
        $this->transaction->update([
            'status' => PaymentGatewayTransactionStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        $resultDTO = new PaymentResultDTO(
            sessionId: 'cs_webhook_test',
            gatewayTransactionId: 'pi_webhook_test',
            amountPaid: 1000.00,
            currency: 'usd',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            paidAt: now()->toDateTimeString(),
            customerEmail: 'billing@school.com',
        );

        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('handleWebhook')->once()->andReturn($resultDTO);

        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_test_dup',
            'type' => 'checkout.session.completed',
        ], [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertOk();

        // No payment should be created (already completed)
        $this->assertDatabaseMissing('invoice_payments', [
            'invoice_id' => $this->invoice->id,
        ]);
    }

    public function test_webhook_returns_ok_for_non_payment_events(): void
    {
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('handleWebhook')->once()->andReturn(null);

        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_other',
            'type' => 'customer.created',
        ], [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertOk();
    }

    public function test_webhook_returns_400_on_signature_failure(): void
    {
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('handleWebhook')
            ->once()
            ->andThrow(new \RuntimeException('Webhook signature verification failed'));

        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_bad_sig',
            'type' => 'checkout.session.completed',
        ], [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400);
    }

    public function test_gateway_logs_are_created_on_webhook(): void
    {
        $resultDTO = new PaymentResultDTO(
            sessionId: 'cs_webhook_test',
            gatewayTransactionId: 'pi_webhook_log_test',
            amountPaid: 1000.00,
            currency: 'usd',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            paidAt: now()->toDateTimeString(),
            customerEmail: 'billing@school.com',
        );

        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('handleWebhook')->once()->andReturn($resultDTO);

        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_log_test',
            'type' => 'checkout.session.completed',
        ], [
            'Stripe-Signature' => 'test_signature',
        ]);

        $this->assertDatabaseHas('payment_gateway_logs', [
            'payment_gateway_transaction_id' => $this->transaction->id,
            'action' => 'webhook_received',
            'direction' => 'incoming',
        ]);

        $this->assertDatabaseHas('payment_gateway_logs', [
            'payment_gateway_transaction_id' => $this->transaction->id,
            'action' => 'webhook_acknowledged',
            'direction' => 'outgoing',
        ]);
    }
}
