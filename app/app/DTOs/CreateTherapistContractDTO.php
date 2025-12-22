<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;
use Carbon\CarbonImmutable;

final class CreateTherapistContractDTO
{
    /**
     * @param  array<int, ContractServiceRateDTO>  $services
     */
    public function __construct(
        public readonly int $therapistId,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly ?string $notes,
        public readonly ContractStatus $status,
        public readonly array $services,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            therapistId: (int) $data['therapist_id'],
            startDate: CarbonImmutable::parse($data['start_date']),
            endDate: CarbonImmutable::parse($data['end_date']),
            notes: $data['notes'] ?? null,
            status: $data['status'] instanceof ContractStatus
                ? $data['status']
                : ContractStatus::from($data['status']),
            services: array_map(
                static fn (array $row) => ContractServiceRateDTO::fromArray($row),
                $data['services'] ?? [],
            ),
        );
    }
}
