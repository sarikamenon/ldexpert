<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateParentProfileDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $emergencyContact = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            emergencyContact: $data['emergency_contact'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'phone' => $this->phone,
            'address' => $this->address,
            'emergency_contact' => $this->emergencyContact,
        ];
    }
}
