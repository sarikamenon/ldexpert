<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PositionStatus;

final class ChangePositionStatusDTO
{
    public function __construct(
        public readonly PositionStatus $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] instanceof PositionStatus
                ? $data['status']
                : PositionStatus::from($data['status']),
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
        ];
    }
}
