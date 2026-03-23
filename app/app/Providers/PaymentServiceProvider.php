<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\Services\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/payment.php'),
            'payment'
        );

        $this->app->singleton(PaymentGatewayManager::class, function (): PaymentGatewayManager {
            return new PaymentGatewayManager;
        });

        $this->app->bind(PaymentGatewayInterface::class, function (): PaymentGatewayInterface {
            /** @var PaymentGatewayManager $manager */
            $manager = $this->app->make(PaymentGatewayManager::class);

            return $manager->gateway();
        });
    }
}
