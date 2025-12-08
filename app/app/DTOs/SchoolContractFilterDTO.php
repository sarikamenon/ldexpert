<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;

final class SchoolContractFilterDTO
{
    public function __construct(
        public readonly ?ContractStatus $status,
        public readonly ?string $search,
        public readonly ?int $schoolId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: isset($data['status'])
                ? ($data['status'] instanceof ContractStatus
                    ? $data['status']
                    : ContractStatus::from($data['status'])
                )
                : null,
            search: $data['search'] ?? null,
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
        );
    }
}
