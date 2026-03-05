<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdateLeadDTO
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $middleName = null,
        public readonly ?string $email = null,
        public readonly ?string $gender = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?int $schoolId = null,
        public readonly ?string $gradeLevel = null,
        public readonly ?string $parentGuardianName = null,
        public readonly ?string $parentGuardianEmail = null,
        public readonly ?string $parentGuardianPhone = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zipCode = null,
        public readonly ?string $source = null,
        public readonly ?string $followUpDate = null,
        public readonly ?string $followUpNotes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            middleName: $data['middle_name'] ?? null,
            email: $data['email'] ?? null,
            gender: $data['gender'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            gradeLevel: $data['grade_level'] ?? null,
            parentGuardianName: $data['parent_guardian_name'] ?? null,
            parentGuardianEmail: $data['parent_guardian_email'] ?? null,
            parentGuardianPhone: $data['parent_guardian_phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            source: $data['source'] ?? null,
            followUpDate: $data['follow_up_date'] ?? null,
            followUpNotes: $data['follow_up_notes'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'gender' => $this->gender,
            'date_of_birth' => $this->dateOfBirth,
            'school_id' => $this->schoolId,
            'grade_level' => $this->gradeLevel,
            'parent_guardian_name' => $this->parentGuardianName,
            'parent_guardian_email' => $this->parentGuardianEmail,
            'parent_guardian_phone' => $this->parentGuardianPhone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'source' => $this->source,
            'follow_up_date' => $this->followUpDate,
            'follow_up_notes' => $this->followUpNotes,
        ];
    }
}
