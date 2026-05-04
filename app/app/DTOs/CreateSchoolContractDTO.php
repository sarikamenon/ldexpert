<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

final class CreateSchoolContractDTO
{
    /**
     * @param  array<int, ContractServiceRateDTO>  $services
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly ?string $notes,
        public readonly ContractStatus $status,
        public readonly array $services,
        public readonly ?UploadedFile $document = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = isset($data['status'])
            ? ($data['status'] instanceof ContractStatus ? $data['status'] : ContractStatus::from($data['status']))
            : ContractStatus::ACTIVE;

        return new self(
            schoolId: (int) $data['school_id'],
            startDate: CarbonImmutable::parse($data['start_date']),
            endDate: CarbonImmutable::parse($data['end_date']),
            notes: $data['notes'] ?? null,
            status: $status,
            services: array_map(
                static fn (array $row) => ContractServiceRateDTO::fromArray($row),
                $data['services'] ?? [],
            ),
            document: ($data['document'] ?? null) instanceof UploadedFile ? $data['document'] : null,
        );
    }
}
