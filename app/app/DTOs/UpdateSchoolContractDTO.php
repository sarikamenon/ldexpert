<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

final class UpdateSchoolContractDTO
{
    /**
     * @param  array<int, ContractServiceRateDTO>  $services
     */
    public function __construct(
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly ?string $notes,
        public readonly ContractStatus $status,
        public readonly array $services,
        public readonly ?UploadedFile $document = null,
        public readonly bool $removeDocument = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?ContractStatus $existingStatus = null): self
    {
        $status = isset($data['status'])
            ? ($data['status'] instanceof ContractStatus ? $data['status'] : ContractStatus::from($data['status']))
            : ($existingStatus ?? ContractStatus::ACTIVE);

        return new self(
            startDate: CarbonImmutable::parse($data['start_date']),
            endDate: CarbonImmutable::parse($data['end_date']),
            notes: $data['notes'] ?? null,
            status: $status,
            services: array_map(
                static fn (array $row) => ContractServiceRateDTO::fromArray($row),
                $data['services'] ?? [],
            ),
            document: $data['document'] instanceof UploadedFile ? $data['document'] : null,
            removeDocument: (bool) ($data['remove_document'] ?? false),
        );
    }
}
