<?php

declare(strict_types=1);

namespace App\DTOs;

final class ChangeLeadStatusDTO
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
        public readonly ?string $followUpDate = null,
        public readonly ?string $followUpNotes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            reason: $data['status_reason'] ?? null,
            followUpDate: $data['follow_up_date'] ?? null,
            followUpNotes: $data['follow_up_notes'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'status_reason' => $this->reason,
            'follow_up_date' => $this->followUpDate,
            'follow_up_notes' => $this->followUpNotes,
        ];
    }
}
