<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TransactionType;

final readonly class CreateLedgerAdjustmentDTO
{
    public function __construct(
        public string $type,
        public int $accountId,
        public TransactionType $transactionType,
        public float $amount,
        public string $recordedAt,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) $data['type'],
            accountId: (int) $data['account_id'],
            transactionType: $data['transaction_type'] instanceof TransactionType
                ? $data['transaction_type']
                : TransactionType::from((string) $data['transaction_type']),
            amount: (float) $data['amount'],
            recordedAt: (string) $data['recorded_at'],
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'account_id' => $this->accountId,
            'transaction_type' => $this->transactionType->value,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
