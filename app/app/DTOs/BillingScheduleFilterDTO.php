<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;

final class BillingScheduleFilterDTO
{
    public function __construct(
        public readonly ?BillingScheduleType $scheduleType = null,
        public readonly ?BillingMode $billingMode = null,
        public readonly ?bool $isActive = null,
        public readonly ?string $frequency = null,
        public readonly int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduleType: ! empty($data['schedule_type'])
                ? BillingScheduleType::from((string) $data['schedule_type'])
                : null,
            billingMode: ! empty($data['billing_mode'])
                ? BillingMode::from((string) $data['billing_mode'])
                : null,
            isActive: isset($data['is_active']) && $data['is_active'] !== ''
                ? (bool) $data['is_active']
                : null,
            frequency: ! empty($data['frequency']) ? (string) $data['frequency'] : null,
            perPage: (int) ($data['per_page'] ?? 15),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schedule_type' => $this->scheduleType?->value,
            'billing_mode' => $this->billingMode?->value,
            'is_active' => $this->isActive,
            'frequency' => $this->frequency,
            'per_page' => $this->perPage,
        ];
    }
}
