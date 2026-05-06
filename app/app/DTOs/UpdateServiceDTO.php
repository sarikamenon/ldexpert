<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Service;

final class UpdateServiceDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $color,
        public readonly bool $isDirectService,
        public readonly bool $isGroupService,
        public readonly bool $isFrequencyService,
        public readonly bool $includeInTho,
        public readonly string $deliveryMode,
        public readonly bool $isBillable,
        public readonly bool $sendEmail,
        public readonly ?int $minDurationMinutes,
        public readonly ?int $maxDurationMinutes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            color: $data['color'] ?? null,
            isDirectService: (bool) ($data['is_direct_service'] ?? false),
            isGroupService: (bool) ($data['is_group_service'] ?? false),
            isFrequencyService: (bool) ($data['is_frequency_service'] ?? false),
            includeInTho: (bool) ($data['include_in_tho'] ?? false),
            deliveryMode: $data['delivery_mode'] ?? Service::defaultDeliveryMode(),
            isBillable: (bool) ($data['is_billable'] ?? true),
            sendEmail: (bool) ($data['send_email'] ?? true),
            minDurationMinutes: isset($data['min_duration_minutes']) ? (int) $data['min_duration_minutes'] : null,
            maxDurationMinutes: isset($data['max_duration_minutes']) ? (int) $data['max_duration_minutes'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_direct_service' => $this->isDirectService,
            'is_group_service' => $this->isGroupService,
            'is_frequency_service' => $this->isFrequencyService,
            'include_in_tho' => $this->includeInTho,
            'delivery_mode' => $this->deliveryMode,
            'is_billable' => $this->isBillable,
            'send_email' => $this->sendEmail,
            'min_duration_minutes' => $this->minDurationMinutes,
            'max_duration_minutes' => $this->maxDurationMinutes,
        ];
    }
}
