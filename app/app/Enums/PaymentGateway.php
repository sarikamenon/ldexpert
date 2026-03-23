<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGateway: string
{
    case STRIPE = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $gateway): string => $gateway->value,
            self::cases()
        );
    }
}
