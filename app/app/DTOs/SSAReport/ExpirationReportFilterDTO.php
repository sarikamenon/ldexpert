<?php

declare(strict_types=1);

namespace App\DTOs\SSAReport;

final class ExpirationReportFilterDTO
{
    public function __construct(
        public readonly int $expirationWindowDays = 30,
        public readonly ?array $schoolIds = null,
        public readonly ?array $therapistIds = null,
        public readonly ?array $serviceIds = null,
        public readonly ?string $bucket = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $schoolIds = isset($data['school_ids']) && is_array($data['school_ids'])
            ? array_filter(array_map('intval', $data['school_ids']))
            : null;

        $therapistIds = isset($data['therapist_ids']) && is_array($data['therapist_ids'])
            ? array_filter(array_map('intval', $data['therapist_ids']))
            : null;

        $serviceIds = isset($data['service_ids']) && is_array($data['service_ids'])
            ? array_filter(array_map('intval', $data['service_ids']))
            : null;

        return new self(
            expirationWindowDays: isset($data['expiration_window_days']) && $data['expiration_window_days'] !== ''
                ? (int) $data['expiration_window_days']
                : 30,
            schoolIds: ! empty($schoolIds) ? $schoolIds : null,
            therapistIds: ! empty($therapistIds) ? $therapistIds : null,
            serviceIds: ! empty($serviceIds) ? $serviceIds : null,
            bucket: $data['bucket'] ?? null,
        );
    }
}
