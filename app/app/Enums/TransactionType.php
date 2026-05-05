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
    case CREDIT_NOTE = 'credit_note';
    case REFUND = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_GENERATED => 'Invoice Generated',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::BILL_GENERATED => 'Bill Generated',
            self::PAYMENT_MADE => 'Payment Made',
            self::EXPENSE => 'Expense',
            self::CREDIT_NOTE => 'Credit Note',
            self::REFUND => 'Refund',
        };
    }

    /**
     * Sign of this transaction's effect on the running ledger balance.
     *
     * +1 increases what the counterparty owes (or what we owe them);
     * -1 decreases it. Single source of truth for the ledger sign convention.
     */
    public function balanceDelta(): int
    {
        return match ($this) {
            self::INVOICE_GENERATED, self::BILL_GENERATED, self::REFUND => 1,
            self::PAYMENT_RECEIVED, self::PAYMENT_MADE, self::CREDIT_NOTE, self::EXPENSE => -1,
        };
    }

    /**
     * Cash direction for this transaction type.
     * Returns null for accrual types (invoice_generated, bill_generated, credit_note, refund)
     * which represent balance changes without actual cash movement.
     */
    public function cashDirection(): ?CashDirection
    {
        return match ($this) {
            self::PAYMENT_RECEIVED => CashDirection::INCOME,
            self::PAYMENT_MADE, self::EXPENSE => CashDirection::EXPENSE,
            default => null,
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
