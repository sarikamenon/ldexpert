<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TherapistBillStatus;

final class TherapistBillFilterDTO
{
    public function __construct(
        public readonly ?int $therapistId,
        public readonly ?TherapistBillStatus $status,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?string $billNumber,
        public readonly int $perPage = 15,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            therapistId: isset($data['therapist_id'])
                ? (int) $data['therapist_id']
                : null,
            status: isset($data['status'])
                ? TherapistBillStatus::from($data['status'])
                : null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            billNumber: $data['bill_number'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'therapist_id' => $this->therapistId,
            'status' => $this->status?->value,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'bill_number' => $this->billNumber,
            'per_page' => $this->perPage,
        ];
    }
}
