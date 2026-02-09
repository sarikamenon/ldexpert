<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SessionLogStatus;

final class SessionLogIndexDTO
{
    public function __construct(
        public readonly ?int $schoolId,
        public readonly ?int $studentId,
        public readonly ?int $therapistId,
        public readonly ?int $serviceId,
        public readonly ?int $ssaId,
        public readonly ?SessionLogStatus $status,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schoolId: $data['school_id'] ?? null,
            studentId: $data['student_id'] ?? null,
            therapistId: $data['therapist_id'] ?? null,
            serviceId: $data['service_id'] ?? null,
            ssaId: $data['ssa_id'] ?? null,
            status: isset($data['status']) && $data['status'] !== null
                ? SessionLogStatus::from($data['status'])
                : null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'student_id' => $this->studentId,
            'therapist_id' => $this->therapistId,
            'service_id' => $this->serviceId,
            'ssa_id' => $this->ssaId,
            'status' => $this->status?->value,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'per_page' => $this->perPage,
        ];
    }
}
