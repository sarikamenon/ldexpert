<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SSAStatus;

final class ChangeSSAStatusDTO
{
    public function __construct(
        public readonly SSAStatus $status,
        public readonly ?string $reason,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] instanceof SSAStatus
            ? $data['status']
            : SSAStatus::from($data['status']);

        return new self(
            status: $status,
            reason: $data['reason'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason,
        ];
    }
}
