<?php

declare(strict_types=1);

use App\Domain\Finance\Services\InvoicePaymentService;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\Services\OnlinePaymentService;
use App\Domain\Payment\Services\PaymentGatewayManager;
use App\DTOs\PaymentResultDTO;
use App\DTOs\PaymentSessionDTO;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayTransactionStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\PaymentGatewayTransaction;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = Mockery::mock(PaymentGatewayInterface::class);
    $this->gateway->shouldReceive('getIdentifier')->andReturn('stripe');

    $this->gatewayManager = Mockery::mock(PaymentGatewayManager::class);
    $this->gatewayManager->shouldReceive('gateway')->andReturn($this->gateway);

    $this->paymentService = app(InvoicePaymentService::class);

    $this->service = new OnlinePaymentService(
        $this->gatewayManager,
        $this->paymentService,
    );

    $school = School::factory()->create();
    $this->invoice = Invoice::factory()->create([
        'school_id' => $school->id,
        'status' => InvoiceStatus::SENT,
        'total' => 500.00,
        'payment_token' => 'test-token-123',
        'school_invoice_email' => 'billing@school.com',
    ]);
});

afterEach(function () {
    Mockery::close();
});

test('createCheckoutSession creates transaction and returns session', function () {
    $sessionDTO = new PaymentSessionDTO(
        sessionId: 'cs_test_123',
        paymentUrl: 'https://checkout.stripe.com/pay/cs_test_123',
        expiresAt: now()->addHours(72)->toDateTimeString(),
        gatewayIdentifier: 'stripe',
    );

    $this->gateway->shouldReceive('createPaymentSession')
        ->once()
        ->andReturn($sessionDTO);

    $result = $this->service->createCheckoutSession($this->invoice, 'billing@school.com');

    expect($result->sessionId)->toBe('cs_test_123');
    expect($result->paymentUrl)->toBe('https://checkout.stripe.com/pay/cs_test_123');

    $transaction = PaymentGatewayTransaction::where('gateway_session_id', 'cs_test_123')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(PaymentGatewayTransactionStatus::PENDING);
    expect((float) $transaction->amount)->toBe(500.00);
    expect($transaction->invoice_id)->toBe($this->invoice->id);

    // Should have 2 logs: request + response
    expect($transaction->logs()->count())->toBe(2);
});

test('processPaymentCompletion records payment and marks transaction completed', function () {
    $transaction = PaymentGatewayTransaction::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'stripe',
        'gateway_session_id' => 'cs_test_456',
        'payment_url' => 'https://checkout.stripe.com/pay/cs_test_456',
        'status' => PaymentGatewayTransactionStatus::PENDING->value,
        'amount' => 500.00,
        'currency' => 'usd',
    ]);

    $resultDTO = new PaymentResultDTO(
        sessionId: 'cs_test_456',
        gatewayTransactionId: 'pi_test_789',
        amountPaid: 500.00,
        currency: 'usd',
        paymentMethod: PaymentMethod::CREDIT_CARD,
        paidAt: now()->toDateTimeString(),
        customerEmail: 'billing@school.com',
    );

    $this->gateway->shouldReceive('retrievePaymentDetails')
        ->once()
        ->with('cs_test_456')
        ->andReturn($resultDTO);

    $payment = $this->service->processPaymentCompletion('cs_test_456');

    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(500.00);
    expect($payment->method)->toBe(PaymentMethod::CREDIT_CARD);
    expect($payment->gateway)->toBe('stripe');
    expect($payment->gateway_transaction_id)->toBe('pi_test_789');

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentGatewayTransactionStatus::COMPLETED);
    expect($transaction->completed_at)->not->toBeNull();

    $this->invoice->refresh();
    expect($this->invoice->isPaid())->toBeTrue();
});

test('processPaymentCompletion is idempotent for completed transactions', function () {
    $transaction = PaymentGatewayTransaction::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'stripe',
        'gateway_session_id' => 'cs_test_completed',
        'payment_url' => 'https://checkout.stripe.com/pay/cs_test_completed',
        'status' => PaymentGatewayTransactionStatus::COMPLETED->value,
        'amount' => 500.00,
        'currency' => 'usd',
        'completed_at' => now(),
    ]);

    // Create the existing payment that was already recorded
    $existingPayment = \App\Models\InvoicePayment::factory()->create([
        'invoice_id' => $this->invoice->id,
        'school_id' => $this->invoice->school_id,
        'amount' => 500.00,
        'payment_gateway_transaction_id' => $transaction->id,
    ]);

    // Gateway should NOT be called again
    $this->gateway->shouldNotReceive('retrievePaymentDetails');

    $payment = $this->service->processPaymentCompletion('cs_test_completed');

    expect($payment->id)->toBe($existingPayment->id);
});

test('processWebhook processes payment for pending transaction', function () {
    $transaction = PaymentGatewayTransaction::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'stripe',
        'gateway_session_id' => 'cs_test_webhook',
        'payment_url' => 'https://checkout.stripe.com/pay/cs_test_webhook',
        'status' => PaymentGatewayTransactionStatus::PENDING->value,
        'amount' => 500.00,
        'currency' => 'usd',
    ]);

    $resultDTO = new PaymentResultDTO(
        sessionId: 'cs_test_webhook',
        gatewayTransactionId: 'pi_test_webhook',
        amountPaid: 500.00,
        currency: 'usd',
        paymentMethod: PaymentMethod::CREDIT_CARD,
        paidAt: now()->toDateTimeString(),
        customerEmail: 'billing@school.com',
    );

    $this->gateway->shouldReceive('handleWebhook')
        ->once()
        ->andReturn($resultDTO);

    $payload = ['id' => 'evt_test_123', 'type' => 'checkout.session.completed'];
    $payment = $this->service->processWebhook($payload, 'sig_test');

    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(500.00);

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentGatewayTransactionStatus::COMPLETED);
});

test('processWebhook skips already completed transaction', function () {
    $transaction = PaymentGatewayTransaction::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'stripe',
        'gateway_session_id' => 'cs_test_already_done',
        'payment_url' => 'https://checkout.stripe.com/pay/cs_test_already_done',
        'status' => PaymentGatewayTransactionStatus::COMPLETED->value,
        'amount' => 500.00,
        'currency' => 'usd',
        'completed_at' => now(),
    ]);

    $resultDTO = new PaymentResultDTO(
        sessionId: 'cs_test_already_done',
        gatewayTransactionId: 'pi_test_already_done',
        amountPaid: 500.00,
        currency: 'usd',
        paymentMethod: PaymentMethod::CREDIT_CARD,
        paidAt: now()->toDateTimeString(),
        customerEmail: 'billing@school.com',
    );

    $this->gateway->shouldReceive('handleWebhook')
        ->once()
        ->andReturn($resultDTO);

    $payload = ['id' => 'evt_test_dup', 'type' => 'checkout.session.completed'];
    $payment = $this->service->processWebhook($payload, 'sig_test');

    expect($payment)->toBeNull();

    // Should have logged webhook_received + webhook_acknowledged
    expect($transaction->logs()->count())->toBe(2);
});

test('processWebhook returns null for non-payment events', function () {
    $this->gateway->shouldReceive('handleWebhook')
        ->once()
        ->andReturn(null);

    $payload = ['id' => 'evt_test_other', 'type' => 'customer.created'];
    $payment = $this->service->processWebhook($payload, 'sig_test');

    expect($payment)->toBeNull();
});
