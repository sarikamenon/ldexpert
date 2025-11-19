<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;

final class ServiceFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?ServiceStatus $status = null,
        public readonly ?ServiceFrequency $frequency = null,
        public readonly ?bool $directService = null,
        public readonly ?bool $groupService = null,
        public readonly ?bool $billable = null,
        public readonly ?string $deliveryMode = null,
        public readonly int $perPage = 25,
    ) {}

    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;
        $frequency = $data['frequency'] ?? null;

        return new self(
            search: $data['search'] ?? null,
            status: $status instanceof ServiceStatus || $status === null || $status === ''
                ? ($status instanceof ServiceStatus ? $status : null)
                : ServiceStatus::from($status),
            frequency: $frequency instanceof ServiceFrequency || $frequency === null || $frequency === ''
                ? ($frequency instanceof ServiceFrequency ? $frequency : null)
                : ServiceFrequency::from($frequency),
            directService: self::parseNullableBool($data['direct_service'] ?? null),
            groupService: self::parseNullableBool($data['group_service'] ?? null),
            billable: self::parseNullableBool($data['is_billable'] ?? $data['billable'] ?? null),
            deliveryMode: ($data['delivery_mode'] ?? '') === '' ? null : (string) $data['delivery_mode'],
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 25,
        );
    }

    private static function parseNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
