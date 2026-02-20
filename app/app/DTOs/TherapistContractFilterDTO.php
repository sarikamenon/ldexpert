<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;

final class TherapistContractFilterDTO
{
    /**
     * @param  array<int>|null  $therapistIds
     */
    public function __construct(
        public readonly ?ContractStatus $status,
        public readonly ?string $search,
        public readonly ?int $therapistId = null,
        public readonly ?array $therapistIds = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $therapistIds = null;
        if (! empty($data['therapist_ids']) && is_array($data['therapist_ids'])) {
            $therapistIds = array_map('intval', $data['therapist_ids']);
        }

        return new self(
            status: isset($data['status'])
                ? ($data['status'] instanceof ContractStatus
                    ? $data['status']
                    : ContractStatus::from($data['status']))
                : null,
            search: $data['search'] ?? null,
            therapistId: isset($data['therapist_id']) ? (int) $data['therapist_id'] : null,
            therapistIds: $therapistIds,
        );
    }
}
