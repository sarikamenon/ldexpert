<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SSAStatus;

final class SSAFilterDTO
{
    /**
     * @param  array<int, SSAStatus>|null  $statuses
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?SSAStatus $status = null,
        public readonly ?array $statuses = null,
        public readonly ?int $studentId = null,
        public readonly ?int $serviceId = null,
        public readonly ?int $therapistId = null,
        public readonly ?int $schoolId = null,
        public readonly int $perPage = 25,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        $rawStatuses = $data['statuses'] ?? null;
        $statuses = null;
        if (is_array($rawStatuses) && $rawStatuses !== []) {
            $statuses = array_values(array_filter(array_map(
                static function (mixed $value): ?SSAStatus {
                    if ($value instanceof SSAStatus) {
                        return $value;
                    }
                    if (is_string($value) && $value !== '') {
                        return SSAStatus::tryFrom($value);
                    }

                    return null;
                },
                $rawStatuses
            )));

            if ($statuses === []) {
                $statuses = null;
            }
        }

        return new self(
            search: $data['search'] ?? null,
            status: $status instanceof SSAStatus || $status === null || $status === ''
                ? ($status instanceof SSAStatus ? $status : null)
                : SSAStatus::from($status),
            statuses: $statuses,
            studentId: isset($data['student_id']) && $data['student_id'] !== ''
                ? (int) $data['student_id']
                : null,
            serviceId: isset($data['service_id']) && $data['service_id'] !== ''
                ? (int) $data['service_id']
                : null,
            therapistId: isset($data['therapist_id']) && $data['therapist_id'] !== ''
                ? (int) $data['therapist_id']
                : null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 25,
        );
    }
}
