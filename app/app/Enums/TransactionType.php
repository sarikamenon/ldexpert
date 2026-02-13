<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case INVOICE_GENERATED = 'invoice_generated';
    case PAYMENT_RECEIVED = 'payment_received';
    case BILL_GENERATED = 'bill_generated';
    case PAYMENT_MADE = 'payment_made';
    case EXPENSE = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_GENERATED => 'Invoice Generated',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::BILL_GENERATED => 'Bill Generated',
            self::PAYMENT_MADE => 'Payment Made',
            self::EXPENSE => 'Expense',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
