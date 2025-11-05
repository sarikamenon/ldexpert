<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateStudentProfileDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $parentId = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $gradeLevel = null,
        public readonly ?string $phone = null,
        public readonly ?string $emergencyContact = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            gradeLevel: $data['grade_level'] ?? null,
            phone: $data['phone'] ?? null,
            emergencyContact: $data['emergency_contact'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'parent_id' => $this->parentId,
            'date_of_birth' => $this->dateOfBirth,
            'grade_level' => $this->gradeLevel,
            'phone' => $this->phone,
            'emergency_contact' => $this->emergencyContact,
        ];
    }
}
