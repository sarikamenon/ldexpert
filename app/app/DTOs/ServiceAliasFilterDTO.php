<?php

declare(strict_types=1);

namespace App\DTOs;

final class ServiceAliasFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $source = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $search = $data['search'] ?? $data['filter_search'] ?? null;
        $source = $data['source'] ?? $data['filter_source'] ?? null;

        return new self(
            search: $search !== null && $search !== '' ? (string) $search : null,
            source: $source !== null && $source !== '' ? (string) $source : null,
        );
    }
}
