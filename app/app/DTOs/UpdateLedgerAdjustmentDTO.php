<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class UpdateLedgerAdjustmentDTO
{
    public function __construct(
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
            amount: (float) $data['amount'],
            recordedAt: (string) $data['recorded_at'],
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
