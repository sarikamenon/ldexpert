<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateStudentProfileDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $parentId = null,
        public readonly ?string $firstName = null,
        public readonly ?string $middleName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $school = null,
        public readonly ?string $idNumber = null,
        public readonly ?string $timezone = null,
        public readonly ?string $gender = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zipCode = null,
        public readonly ?string $parentGuardianName = null,
        public readonly ?string $parentGuardianEmail = null,
        public readonly ?string $parentGuardianPhone = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $gradeLevel = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            firstName: $data['first_name'] ?? null,
            middleName: $data['middle_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            school: $data['school'] ?? null,
            idNumber: $data['id_number'] ?? null,
            timezone: $data['timezone'] ?? null,
            gender: $data['gender'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            parentGuardianName: $data['parent_guardian_name'] ?? null,
            parentGuardianEmail: $data['parent_guardian_email'] ?? null,
            parentGuardianPhone: $data['parent_guardian_phone'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            gradeLevel: $data['grade_level'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'parent_id' => $this->parentId,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'school' => $this->school,
            'id_number' => $this->idNumber,
            'timezone' => $this->timezone,
            'gender' => $this->gender,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'parent_guardian_name' => $this->parentGuardianName,
            'parent_guardian_email' => $this->parentGuardianEmail,
            'parent_guardian_phone' => $this->parentGuardianPhone,
            'date_of_birth' => $this->dateOfBirth,
            'grade_level' => $this->gradeLevel,
        ];
    }
}
