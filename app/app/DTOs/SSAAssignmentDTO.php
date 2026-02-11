<?php

declare(strict_types=1);

namespace App\DTOs;

final class SSAAssignmentDTO
{
    public function __construct(
        public readonly int $therapistId,
        public readonly ?string $reason,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            therapistId: (int) $data['therapist_id'],
            reason: $data['reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'therapist_id' => $this->therapistId,
            'reason' => $this->reason,
        ];
    }
}
