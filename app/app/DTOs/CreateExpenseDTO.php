<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class CreateExpenseDTO
{
    public function __construct(
        public int $expenseCategoryId,
        public string $expenseDate,
        public float $amount,
        public ?string $vendorPayee = null,
        public ?string $description = null,
        public ?string $reference = null,
        public ?int $createdById = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            expenseCategoryId: (int) $data['expense_category_id'],
            expenseDate: $data['expense_date'],
            amount: (float) $data['amount'],
            vendorPayee: $data['vendor_payee'] ?? null,
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            createdById: $data['created_by_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'expense_category_id' => $this->expenseCategoryId,
            'expense_date' => $this->expenseDate,
            'amount' => $this->amount,
            'vendor_payee' => $this->vendorPayee,
            'description' => $this->description,
            'reference' => $this->reference,
            'created_by_id' => $this->createdById,
        ];
    }
}
