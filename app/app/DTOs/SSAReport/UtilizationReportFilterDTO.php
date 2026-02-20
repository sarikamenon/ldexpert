<?php

declare(strict_types=1);

namespace App\DTOs\SSAReport;

use App\Enums\SSAStatus;
use Carbon\Carbon;

final class UtilizationReportFilterDTO
{
    public function __construct(
        public readonly ?Carbon $startDate = null,
        public readonly ?Carbon $endDate = null,
        public readonly ?array $schoolIds = null,
        public readonly ?array $therapistIds = null,
        public readonly ?array $serviceIds = null,
        public readonly ?array $statuses = null,
        public readonly ?string $utilizationBand = null,
        public readonly ?string $gradeLevel = null,
        public readonly int $perPage = 25,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $startDate = isset($data['start_date']) && $data['start_date'] !== ''
            ? Carbon::parse($data['start_date'])
            : null;

        $endDate = isset($data['end_date']) && $data['end_date'] !== ''
            ? Carbon::parse($data['end_date'])
            : null;

        $schoolIds = isset($data['school_ids']) && is_array($data['school_ids'])
            ? array_filter(array_map('intval', $data['school_ids']))
            : null;

        $therapistIds = isset($data['therapist_ids']) && is_array($data['therapist_ids'])
            ? array_filter(array_map('intval', $data['therapist_ids']))
            : null;

        $serviceIds = isset($data['service_ids']) && is_array($data['service_ids'])
            ? array_filter(array_map('intval', $data['service_ids']))
            : null;

        $statuses = null;
        if (isset($data['statuses']) && is_array($data['statuses'])) {
            $statuses = array_filter(
                array_map(
                    static fn ($status) => $status instanceof SSAStatus
                        ? $status
                        : (is_string($status) && $status !== '' ? SSAStatus::tryFrom($status) : null),
                    $data['statuses']
                )
            );
            $statuses = ! empty($statuses) ? array_values($statuses) : null;
        }

        return new self(
            startDate: $startDate,
            endDate: $endDate,
            schoolIds: ! empty($schoolIds) ? $schoolIds : null,
            therapistIds: ! empty($therapistIds) ? $therapistIds : null,
            serviceIds: ! empty($serviceIds) ? $serviceIds : null,
            statuses: $statuses,
            utilizationBand: $data['utilization_band'] ?? null,
            gradeLevel: $data['grade_level'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 25,
        );
    }
}
