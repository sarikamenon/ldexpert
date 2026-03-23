<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Infrastructure\Payment\StripePaymentGateway;

test('getIdentifier returns stripe', function () {
    $gateway = new StripePaymentGateway([
        'secret_key' => 'sk_test_fake',
        'publishable_key' => 'pk_test_fake',
        'webhook_secret' => 'whsec_test_fake',
    ]);

    expect($gateway->getIdentifier())->toBe('stripe');
});

test('stripe payment method mapping covers expected types', function () {
    // Use reflection to test the private mapPaymentMethod
    $gateway = new StripePaymentGateway([
        'secret_key' => 'sk_test_fake',
        'publishable_key' => 'pk_test_fake',
        'webhook_secret' => 'whsec_test_fake',
    ]);

    $method = new \ReflectionMethod($gateway, 'mapPaymentMethod');
    $method->setAccessible(true);

    // null payment intent returns OTHER
    expect($method->invoke($gateway, null))->toBe(PaymentMethod::OTHER);
});
