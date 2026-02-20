<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;

final class SchoolContractFilterDTO
{
    /**
     * @param  array<int>|null  $schoolIds
     */
    public function __construct(
        public readonly ?ContractStatus $status,
        public readonly ?string $search,
        public readonly ?int $schoolId = null,
        public readonly ?array $schoolIds = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $schoolIds = null;
        if (! empty($data['school_ids']) && is_array($data['school_ids'])) {
            $schoolIds = array_map('intval', $data['school_ids']);
        }

        return new self(
            status: isset($data['status'])
                ? ($data['status'] instanceof ContractStatus
                    ? $data['status']
                    : ContractStatus::from($data['status'])
                )
                : null,
            search: $data['search'] ?? null,
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
            schoolIds: $schoolIds,
        );
    }
}
