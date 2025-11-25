<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdateStudentDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly ?string $middleName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly ?string $gender,
        public readonly string $dateOfBirth,
        public readonly ?int $schoolId,
        public readonly ?string $idNumber,
        public readonly string $timezone,
        public readonly ?string $gradeLevel,
        public readonly ?string $parentGuardianName,
        public readonly ?string $parentGuardianEmail,
        public readonly ?string $parentGuardianPhone,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $zipCode,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            middleName: $data['middle_name'] ?? null,
            lastName: $data['last_name'],
            email: $data['email'],
            gender: $data['gender'] ?? null,
            dateOfBirth: $data['date_of_birth'],
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
            idNumber: $data['id_number'] ?? null,
            timezone: $data['timezone'],
            gradeLevel: $data['grade_level'] ?? null,
            parentGuardianName: $data['parent_guardian_name'] ?? null,
            parentGuardianEmail: $data['parent_guardian_email'] ?? null,
            parentGuardianPhone: $data['parent_guardian_phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
        );
    }

    public function toUserArray(): array
    {
        return [
            'name' => trim($this->firstName . ' ' . ($this->middleName ? $this->middleName . ' ' : '') . $this->lastName),
            'email' => $this->email,
        ];
    }

    public function toProfileArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'school_id' => $this->schoolId,
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

