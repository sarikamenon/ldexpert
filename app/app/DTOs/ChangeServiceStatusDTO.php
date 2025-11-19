<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceStatus;

final class ChangeServiceStatusDTO
{
    public function __construct(
        public readonly ServiceStatus $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] instanceof ServiceStatus
                ? $data['status']
                : ServiceStatus::from($data['status']),
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
        ];
    }
}
