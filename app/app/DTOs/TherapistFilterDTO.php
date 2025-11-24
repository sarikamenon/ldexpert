<?php

declare(strict_types=1);

namespace App\DTOs;

final class TherapistFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?string $position = null,
        public readonly ?int $schoolId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            position: $data['position'] ?? null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'position' => $this->position,
            'school_id' => $this->schoolId,
        ];
    }
}
