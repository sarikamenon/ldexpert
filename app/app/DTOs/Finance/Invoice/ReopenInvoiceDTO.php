<?php

declare(strict_types=1);

namespace App\DTOs\Finance\Invoice;

final class ReopenInvoiceDTO
{
    public function __construct(
        public readonly string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reason: (string) ($data['reason'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
