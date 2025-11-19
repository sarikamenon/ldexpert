<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;
use App\Models\Service;

final class CreateServiceDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $directService,
        public readonly bool $groupService,
        public readonly ServiceFrequency $frequency,
        public readonly string $deliveryMode,
        public readonly bool $isBillable,
        public readonly ?int $minDurationMinutes,
        public readonly ?int $maxDurationMinutes,
        public readonly ServiceStatus $status,
    ) {}

    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? ServiceStatus::ACTIVE;
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            directService: (bool) ($data['direct_service'] ?? false),
            groupService: (bool) ($data['group_service'] ?? false),
            frequency: $data['frequency'] instanceof ServiceFrequency
                ? $data['frequency']
                : ServiceFrequency::from($data['frequency']),
            deliveryMode: $data['delivery_mode'] ?? Service::defaultDeliveryMode(),
            isBillable: (bool) ($data['is_billable'] ?? true),
            minDurationMinutes: isset($data['min_duration_minutes']) ? (int) $data['min_duration_minutes'] : null,
            maxDurationMinutes: isset($data['max_duration_minutes']) ? (int) $data['max_duration_minutes'] : null,
            status: $status instanceof ServiceStatus ? $status : ServiceStatus::from($status),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'direct_service' => $this->directService,
            'group_service' => $this->groupService,
            'frequency' => $this->frequency->value,
            'delivery_mode' => $this->deliveryMode,
            'is_billable' => $this->isBillable,
            'min_duration_minutes' => $this->minDurationMinutes,
            'max_duration_minutes' => $this->maxDurationMinutes,
            'status' => $this->status->value,
        ];
    }
}
