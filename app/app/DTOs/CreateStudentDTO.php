<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateStudentDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly ?string $middleName,
        public readonly string $lastName,
        public readonly string $username,
        public readonly string $email,
        public readonly ?string $gender,
        public readonly ?string $dateOfBirth,
        public readonly ?int $schoolId,
        public readonly ?string $idNumber,
        public readonly string $timezone,
        public readonly ?string $gradeLevel,
        public readonly ?string $parentGuardianName,
        public readonly ?string $parentGuardianEmail,
        public readonly ?string $parentGuardianPhone,
        public readonly ?string $scheduleEmail,
        public readonly ?string $parentGuardian2Name,
        public readonly ?string $parentGuardian2Email,
        public readonly ?string $parentGuardian2Phone,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $zipCode,
        public readonly string $password,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            middleName: $data['middle_name'] ?? null,
            lastName: $data['last_name'],
            username: $data['username'],
            email: $data['email'],
            gender: $data['gender'] ?? null,
            dateOfBirth: ($data['date_of_birth'] ?? null) ?: null,
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
            idNumber: $data['id_number'] ?? null,
            timezone: $data['timezone'],
            gradeLevel: $data['grade_level'] ?? null,
            parentGuardianName: $data['parent_guardian_name'] ?? null,
            parentGuardianEmail: $data['parent_guardian_email'] ?? null,
            parentGuardianPhone: $data['parent_guardian_phone'] ?? null,
            scheduleEmail: $data['schedule_email'] ?? null,
            parentGuardian2Name: $data['parent_guardian_2_name'] ?? null,
            parentGuardian2Email: $data['parent_guardian_2_email'] ?? null,
            parentGuardian2Phone: $data['parent_guardian_2_phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            password: $data['password'],
        );
    }

    /** @return array<string, mixed> */
    public function toUserArray(): array
    {
        return [
            'name' => trim($this->firstName.' '.($this->middleName ? $this->middleName.' ' : '').$this->lastName),
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'role' => 'student',
            'status' => 'active',
            'timezone' => $this->timezone,
        ];
    }

    /** @return array<string, mixed> */
    public function toProfileArray(int $userId): array
    {
        return [
            'user_id' => $userId,
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
            'schedule_email' => $this->scheduleEmail,
            'parent_guardian_2_name' => $this->parentGuardian2Name,
            'parent_guardian_2_email' => $this->parentGuardian2Email,
            'parent_guardian_2_phone' => $this->parentGuardian2Phone,
            'date_of_birth' => $this->dateOfBirth,
            'grade_level' => $this->gradeLevel,
        ];
    }
}
