<?php

declare(strict_types=1);

namespace App\DTOs;

final class ScheduleFilterDTO
{
    /**
     * @param array<int, int>|null $therapistIds
     * @param array<int, int>|null $studentIds
     */
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?int $schoolId = null,
        public readonly ?int $studentId = null,
        public readonly ?string $status = null,
        public readonly ?string $billingStatus = null,
        public readonly ?int $ssaId = null,
        public readonly ?int $serviceId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?int $therapistId = null,
        public readonly ?array $therapistIds = null,
        public readonly ?array $studentIds = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            date: $data['date'] ?? null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            studentId: isset($data['student_id']) && $data['student_id'] !== ''
                ? (int) $data['student_id']
                : null,
            status: $data['status'] ?? null,
            billingStatus: $data['billing_status'] ?? null,
            ssaId: isset($data['ssa_id']) && $data['ssa_id'] !== ''
                ? (int) $data['ssa_id']
                : null,
            serviceId: isset($data['service_id']) && $data['service_id'] !== ''
                ? (int) $data['service_id']
                : null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            therapistId: isset($data['therapist_id']) && $data['therapist_id'] !== ''
                ? (int) $data['therapist_id']
                : null,
            therapistIds: isset($data['therapist_ids']) && is_array($data['therapist_ids']) && $data['therapist_ids'] !== []
                ? array_map('intval', $data['therapist_ids'])
                : null,
            studentIds: isset($data['student_ids']) && is_array($data['student_ids']) && $data['student_ids'] !== []
                ? array_map('intval', $data['student_ids'])
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'school_id' => $this->schoolId,
            'student_id' => $this->studentId,
            'status' => $this->status,
            'billing_status' => $this->billingStatus,
            'ssa_id' => $this->ssaId,
            'service_id' => $this->serviceId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'therapist_id' => $this->therapistId,
            'therapist_ids' => $this->therapistIds,
            'student_ids' => $this->studentIds,
        ];
    }
}
