<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CHECK = 'check';
    case BANK_TRANSFER = 'bank_transfer';
    case ACH = 'ach';
    case WIRE = 'wire';
    case DIRECT_DEPOSIT = 'direct_deposit';
    case CASH = 'cash';
    case CREDIT_CARD = 'credit_card';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CHECK => 'Check',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::ACH => 'ACH',
            self::WIRE => 'Wire Transfer',
            self::DIRECT_DEPOSIT => 'Direct Deposit',
            self::CASH => 'Cash',
            self::CREDIT_CARD => 'Credit Card',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $method): string => $method->value,
            self::cases()
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
