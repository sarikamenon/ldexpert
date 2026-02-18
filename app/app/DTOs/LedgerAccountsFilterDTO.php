<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class LedgerAccountsFilterDTO
{
    public function __construct(
        public string $type,
        public ?string $search,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? 'schools'),
            search: isset($data['search']) && $data['search'] !== ''
                ? (string) $data['search']
                : null,
        );
    }
}

