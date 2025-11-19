<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ContractStatus;

final class ChangeContractStatusDTO
{
    public function __construct(
        public readonly ContractStatus $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] instanceof ContractStatus
                ? $data['status']
                : ContractStatus::from($data['status']),
        );
    }
}
