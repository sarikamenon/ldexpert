<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SSAStatus;

final class SSAFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?SSAStatus $status = null,
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

        return new self(
            search: $data['search'] ?? null,
            status: $status instanceof SSAStatus || $status === null || $status === ''
                ? ($status instanceof SSAStatus ? $status : null)
                : SSAStatus::from($status),
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
