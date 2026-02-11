<?php

declare(strict_types=1);

namespace App\DTOs;

final class ScheduleFilterDTO
{
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
    ) {}

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
        );
    }

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
        ];
    }
}
