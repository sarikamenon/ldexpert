<?php

declare(strict_types=1);

namespace App\DTOs;

final class TherapistFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?int $positionId = null,
        public readonly ?int $schoolId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            positionId: isset($data['position_id']) && $data['position_id'] !== ''
                ? (int) $data['position_id']
                : null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'position_id' => $this->positionId,
            'school_id' => $this->schoolId,
        ];
    }
}
