<?php

declare(strict_types=1);

namespace App\DTOs;

final class LeadFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?string $source = null,
        public readonly ?int $schoolId = null,
        public readonly int $perPage = 25,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: isset($data['status']) && $data['status'] !== '' ? $data['status'] : null,
            source: isset($data['source']) && $data['source'] !== '' ? $data['source'] : null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            perPage: (int) ($data['per_page'] ?? 25),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'source' => $this->source,
            'school_id' => $this->schoolId,
            'per_page' => $this->perPage,
        ];
    }
}
