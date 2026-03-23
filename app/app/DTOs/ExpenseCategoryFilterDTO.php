<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ExpenseCategoryFilterDTO
{
    public function __construct(
        public ?string $search,
        public ?string $status,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
