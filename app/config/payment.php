<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | for processing online payments. You may change this to any of the
    | gateways defined in the "gateways" array below.
    |
    */
    'default' => env('PAYMENT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the payment gateways used by your
    | application. To add a new gateway, add a new key to this array
    | and implement PaymentGatewayInterface.
    |
    */
    'gateways' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('PAYMENT_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Payment Link Expiry
    |--------------------------------------------------------------------------
    |
    | The number of hours a Stripe checkout session remains valid (max 24).
    | If set to 0, defaults to 24 hours (Stripe's maximum).
    |
    */
    'link_expiry_hours' => (int) env('PAYMENT_LINK_EXPIRY', 24),
];
