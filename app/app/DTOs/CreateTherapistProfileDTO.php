<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateTherapistProfileDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $phone = null,
        public readonly ?string $licenseNumber = null,
        public readonly ?string $specialization = null,
        public readonly ?int $yearsOfExperience = null,
        public readonly ?string $bio = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            phone: $data['phone'] ?? null,
            licenseNumber: $data['license_number'] ?? null,
            specialization: $data['specialization'] ?? null,
            yearsOfExperience: isset($data['years_of_experience']) ? (int) $data['years_of_experience'] : null,
            bio: $data['bio'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'phone' => $this->phone,
            'license_number' => $this->licenseNumber,
            'specialization' => $this->specialization,
            'years_of_experience' => $this->yearsOfExperience,
            'bio' => $this->bio,
        ];
    }
}
