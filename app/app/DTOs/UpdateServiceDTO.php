<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Service;

final class UpdateServiceDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isDirectService,
        public readonly bool $isGroupService,
        public readonly bool $isFrequencyService,
        public readonly string $deliveryMode,
        public readonly bool $isBillable,
        public readonly ?int $minDurationMinutes,
        public readonly ?int $maxDurationMinutes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            isDirectService: (bool) ($data['is_direct_service'] ?? false),
            isGroupService: (bool) ($data['is_group_service'] ?? false),
            isFrequencyService: (bool) ($data['is_frequency_service'] ?? false),
            deliveryMode: $data['delivery_mode'] ?? Service::defaultDeliveryMode(),
            isBillable: (bool) ($data['is_billable'] ?? true),
            minDurationMinutes: isset($data['min_duration_minutes']) ? (int) $data['min_duration_minutes'] : null,
            maxDurationMinutes: isset($data['max_duration_minutes']) ? (int) $data['max_duration_minutes'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'is_direct_service' => $this->isDirectService,
            'is_group_service' => $this->isGroupService,
            'is_frequency_service' => $this->isFrequencyService,
            'delivery_mode' => $this->deliveryMode,
            'is_billable' => $this->isBillable,
            'min_duration_minutes' => $this->minDurationMinutes,
            'max_duration_minutes' => $this->maxDurationMinutes,
        ];
    }
}
