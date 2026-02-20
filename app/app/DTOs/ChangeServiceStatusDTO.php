<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceStatus;

final class ChangeServiceStatusDTO
{
    public function __construct(
        public readonly ServiceStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] instanceof ServiceStatus
                ? $data['status']
                : ServiceStatus::from($data['status']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
        ];
    }
}
