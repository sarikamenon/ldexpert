<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;

final class ContractFilterDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?ContractStatus $status,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        return new self(
            search: $data['search'] ?? null,
            status: $status ? ContractStatus::from($status) : null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
        );
    }
}
