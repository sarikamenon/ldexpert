<?php

declare(strict_types=1);

namespace App\DTOs;

final class ChangeStudentStatusDTO
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            reason: $data['reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
        ];
    }
}
