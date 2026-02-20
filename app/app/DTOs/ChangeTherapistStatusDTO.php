<?php

declare(strict_types=1);

namespace App\DTOs;

final class ChangeTherapistStatusDTO
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            reason: $data['reason'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'status_reason' => $this->reason,
        ];
    }
}
