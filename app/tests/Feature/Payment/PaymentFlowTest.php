<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentResultDTO;
use App\DTOs\PaymentSessionDTO;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayTransactionStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\PaymentGatewayTransaction;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    private string $paymentToken;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::factory()->create();
        $this->paymentToken = 'test-payment-token-abc';

        $this->invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'status' => InvoiceStatus::SENT,
            'total' => 750.00,
            'payment_token' => $this->paymentToken,
            'school_display_name' => 'Test School',
            'school_invoice_email' => 'billing@test-school.com',
            'company_name' => 'LD Expert LLC',
        ]);
    }

    public function test_payment_page_shows_invoice_details(): void
    {
        $response = $this->get(route('payment.show', $this->paymentToken));

        $response->assertStatus(200);
        $response->assertViewIs('payment.checkout');
        $response->assertSee($this->invoice->invoice_number);
        $response->assertSee('750.00');
    }

    public function test_payment_page_returns_404_for_invalid_token(): void
    {
        $response = $this->get(route('payment.show', 'invalid-token'));

        $response->assertStatus(404);
    }

    public function test_payment_page_returns_404_for_draft_invoice(): void
    {
        $this->invoice->update(['status' => InvoiceStatus::DRAFT->value]);

        $response = $this->get(route('payment.show', $this->paymentToken));

        $response->assertStatus(404);
    }

    public function test_payment_page_shows_already_paid_for_paid_invoice(): void
    {
        $this->invoice->update([
            'status' => InvoiceStatus::PAID->value,
            'paid_at' => now(),
        ]);

        $response = $this->get(route('payment.show', $this->paymentToken));

        $response->assertStatus(200);
        $response->assertViewIs('payment.already-paid');
    }

    public function test_checkout_redirects_to_gateway(): void
    {
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('getIdentifier')->andReturn('stripe');
        $gateway->shouldReceive('createPaymentSession')
            ->once()
            ->andReturn(new PaymentSessionDTO(
                sessionId: 'cs_test_checkout',
                paymentUrl: 'https://checkout.stripe.com/pay/cs_test_checkout',
                expiresAt: now()->addHours(72)->toDateTimeString(),
                gatewayIdentifier: 'stripe',
            ));

        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        // Re-bind the manager to return our mock
        $manager = \Mockery::mock(\App\Domain\Payment\Services\PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(\App\Domain\Payment\Services\PaymentGatewayManager::class, $manager);

        $response = $this->post(route('payment.checkout', $this->paymentToken));

        $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_checkout');
    }

    public function test_checkout_redirect_back_on_gateway_error(): void
    {
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('getIdentifier')->andReturn('stripe');
        $gateway->shouldReceive('createPaymentSession')
            ->once()
            ->andThrow(new \RuntimeException('Stripe error'));

        $manager = \Mockery::mock(\App\Domain\Payment\Services\PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(\App\Domain\Payment\Services\PaymentGatewayManager::class, $manager);

        $response = $this->post(route('payment.checkout', $this->paymentToken));

        $response->assertRedirect(route('payment.show', $this->paymentToken));
        $response->assertSessionHas('error');
    }

    public function test_success_page_processes_payment(): void
    {
        $transaction = PaymentGatewayTransaction::create([
            'invoice_id' => $this->invoice->id,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_test_success',
            'payment_url' => 'https://checkout.stripe.com/pay/cs_test_success',
            'status' => PaymentGatewayTransactionStatus::PENDING->value,
            'amount' => 750.00,
            'currency' => 'usd',
        ]);

        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('getIdentifier')->andReturn('stripe');
        $gateway->shouldReceive('retrievePaymentDetails')
            ->once()
            ->with('cs_test_success')
            ->andReturn(new PaymentResultDTO(
                sessionId: 'cs_test_success',
                gatewayTransactionId: 'pi_test_success',
                amountPaid: 750.00,
                currency: 'usd',
                paymentMethod: PaymentMethod::CREDIT_CARD,
                paidAt: now()->toDateTimeString(),
                customerEmail: 'billing@test-school.com',
            ));

        $manager = \Mockery::mock(\App\Domain\Payment\Services\PaymentGatewayManager::class);
        $manager->shouldReceive('gateway')->andReturn($gateway);
        $this->app->instance(\App\Domain\Payment\Services\PaymentGatewayManager::class, $manager);

        $response = $this->get(route('payment.success', ['session_id' => 'cs_test_success']));

        $response->assertStatus(200);
        $response->assertViewIs('payment.success');

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $this->invoice->id,
            'amount' => 750.00,
            'gateway' => 'stripe',
            'gateway_transaction_id' => 'pi_test_success',
        ]);

        $transaction->refresh();
        $this->assertEquals(PaymentGatewayTransactionStatus::COMPLETED, $transaction->status);

        $this->invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $this->invoice->status);
    }

    public function test_success_page_returns_404_without_session_id(): void
    {
        $response = $this->get(route('payment.success'));

        $response->assertStatus(404);
    }

    public function test_cancel_page_shows_retry_link(): void
    {
        $response = $this->get(route('payment.cancel', $this->paymentToken));

        $response->assertStatus(200);
        $response->assertViewIs('payment.cancel');
        $response->assertSee($this->invoice->invoice_number);
    }
}
