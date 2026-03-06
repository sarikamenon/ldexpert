<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Infrastructure\Payment\StripePaymentGateway;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    private array $gateways = [];

    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?? $this->getDefaultGateway();

        if (! isset($this->gateways[$name])) {
            $this->gateways[$name] = $this->resolve($name);
        }

        return $this->gateways[$name];
    }

    public function getDefaultGateway(): string
    {
        return (string) config('payment.default', 'stripe');
    }

    private function resolve(string $name): PaymentGatewayInterface
    {
        /** @var array<string, mixed> $gatewayConfig */
        $gatewayConfig = config("payment.gateways.{$name}", []);

        if (empty($gatewayConfig)) {
            throw new \InvalidArgumentException("Payment gateway [{$name}] is not configured.");
        }

        return match ($name) {
            'stripe' => new StripePaymentGateway($gatewayConfig),
            default => throw new \InvalidArgumentException("Unsupported payment gateway [{$name}]."),
        };
    }
}
