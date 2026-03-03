<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdatePositionDTO
{
    /** @param array<int> $serviceIds */
    public function __construct(
        public readonly string $name,
        public readonly array $serviceIds = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            serviceIds: $data['service_ids'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
