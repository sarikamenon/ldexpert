<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceStatus;

final class ServiceFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?ServiceStatus $status = null,
        public readonly ?bool $isFrequencyService = null,
        public readonly ?bool $isDirectService = null,
        public readonly ?bool $isGroupService = null,
        public readonly ?bool $billable = null,
        public readonly int $perPage = 25,
    ) {}

    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        return new self(
            search: $data['search'] ?? null,
            status: $status instanceof ServiceStatus || $status === null || $status === ''
                ? ($status instanceof ServiceStatus ? $status : null)
                : ServiceStatus::from($status),
            isFrequencyService: self::parseNullableBool($data['is_frequency_service'] ?? $data['frequency'] ?? null),
            isDirectService: self::parseNullableBool($data['is_direct_service'] ?? $data['direct_service'] ?? null),
            isGroupService: self::parseNullableBool($data['is_group_service'] ?? $data['group_service'] ?? null),
            billable: self::parseNullableBool($data['is_billable'] ?? $data['billable'] ?? null),
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
