<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class InvoicePaymentFilterDTO
{
    public function __construct(
        public ?string $fromDate,
        public ?string $toDate,
        public ?string $method,
        public ?string $search,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            method: $data['method'] ?? null,
            search: $data['search'] ?? null,
        );
    }
}
