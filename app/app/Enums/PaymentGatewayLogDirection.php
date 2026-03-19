<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGatewayLogDirection: string
{
    case OUTGOING = 'outgoing';
    case INCOMING = 'incoming';

    public function label(): string
    {
        return match ($this) {
            self::OUTGOING => 'Outgoing',
            self::INCOMING => 'Incoming',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $direction): string => $direction->value,
            self::cases()
        );
    }
}
