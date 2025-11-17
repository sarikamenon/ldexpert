<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SchoolStatus;

final class ChangeSchoolStatusDTO
{
    public function __construct(
        public readonly SchoolStatus $status,
        public readonly ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] instanceof SchoolStatus
                ? $data['status']
                : SchoolStatus::from($data['status']),
            reason: $data['status_reason'] ?? $data['reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'status_reason' => $this->reason,
        ];
    }
}
