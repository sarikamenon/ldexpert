<?php

declare(strict_types=1);

use App\Enums\CashDirection;
use App\Enums\TransactionType;

test('payment_received maps to income direction', function (): void {
    expect(TransactionType::PAYMENT_RECEIVED->cashDirection())->toBe(CashDirection::INCOME);
});

test('payment_made maps to expense direction', function (): void {
    expect(TransactionType::PAYMENT_MADE->cashDirection())->toBe(CashDirection::EXPENSE);
});

test('expense maps to expense direction', function (): void {
    expect(TransactionType::EXPENSE->cashDirection())->toBe(CashDirection::EXPENSE);
});

test('invoice_generated is an accrual with null direction', function (): void {
    expect(TransactionType::INVOICE_GENERATED->cashDirection())->toBeNull();
});

test('bill_generated is an accrual with null direction', function (): void {
    expect(TransactionType::BILL_GENERATED->cashDirection())->toBeNull();
});

test('credit_note is an accrual with null direction', function (): void {
    expect(TransactionType::CREDIT_NOTE->cashDirection())->toBeNull();
});

test('refund is an accrual with null direction', function (): void {
    expect(TransactionType::REFUND->cashDirection())->toBeNull();
});

test('only payment_received payment_made and expense have a cash direction', function (): void {
    $cashTypes = array_filter(
        TransactionType::cases(),
        static fn (TransactionType $t): bool => $t->cashDirection() !== null,
    );

    expect($cashTypes)->toHaveCount(3);
});
