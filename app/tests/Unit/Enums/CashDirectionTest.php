<?php

declare(strict_types=1);

use App\Enums\CashDirection;

test('income label returns Income', function (): void {
    expect(CashDirection::INCOME->label())->toBe('Income');
});

test('expense label returns Expense', function (): void {
    expect(CashDirection::EXPENSE->label())->toBe('Expense');
});

test('income value is income string', function (): void {
    expect(CashDirection::INCOME->value)->toBe('income');
});

test('expense value is expense string', function (): void {
    expect(CashDirection::EXPENSE->value)->toBe('expense');
});

test('cash direction has exactly two cases', function (): void {
    expect(CashDirection::cases())->toHaveCount(2);
});
