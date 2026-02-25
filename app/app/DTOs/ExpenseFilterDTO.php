<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ExpenseFilterDTO
{
    public function __construct(
        public ?int $categoryId,
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?string $search,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            categoryId: isset($data['category_id']) && $data['category_id'] !== ''
                ? (int) $data['category_id']
                : null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            search: $data['search'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
