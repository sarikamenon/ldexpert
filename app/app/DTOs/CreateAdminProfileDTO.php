<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateAdminProfileDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $department = null,
        public readonly ?string $phone = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            department: $data['department'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'department' => $this->department,
            'phone' => $this->phone,
        ];
    }
}
