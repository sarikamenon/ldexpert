<?php

declare(strict_types=1);

namespace App\DTOs\SSAReport;

use App\Enums\SSAStatus;

final class CaseloadReportFilterDTO
{
    public function __construct(
        public readonly ?array $schoolIds = null,
        public readonly ?array $therapistIds = null,
        public readonly ?array $serviceIds = null,
        public readonly ?SSAStatus $status = null,
        public readonly ?int $minMinutesPerWeek = null,
        public readonly ?int $maxMinutesPerWeek = null,
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

        $status = null;
        if (isset($data['status']) && $data['status'] !== '') {
            $status = $data['status'] instanceof SSAStatus
                ? $data['status']
                : SSAStatus::tryFrom($data['status']);
        }

        return new self(
            schoolIds: ! empty($schoolIds) ? $schoolIds : null,
            therapistIds: ! empty($therapistIds) ? $therapistIds : null,
            serviceIds: ! empty($serviceIds) ? $serviceIds : null,
            status: $status,
            minMinutesPerWeek: isset($data['min_minutes_per_week']) && $data['min_minutes_per_week'] !== ''
                ? (int) $data['min_minutes_per_week']
                : null,
            maxMinutesPerWeek: isset($data['max_minutes_per_week']) && $data['max_minutes_per_week'] !== ''
                ? (int) $data['max_minutes_per_week']
                : null,
        );
    }
}
