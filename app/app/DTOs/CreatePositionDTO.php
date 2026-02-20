<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PositionStatus;

final class CreatePositionDTO
{
    public function __construct(
        public readonly string $name,
        public readonly array $serviceIds,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            serviceIds: $data['service_ids'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => PositionStatus::ACTIVE->value,
        ];
    }
}
