<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Lead;

final class ConvertLeadDTO
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly int $schoolId,
        public readonly string $idNumber,
        public readonly string $timezone,
        public readonly string $gradeLevel,
        public readonly string $gender,
        public readonly string $city,
        public readonly string $state,
        public readonly string $zipCode,
        public readonly string $password,
        public readonly ?string $scheduleEmail = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'],
            email: $data['email'],
            schoolId: (int) $data['school_id'],
            idNumber: $data['id_number'],
            timezone: $data['timezone'],
            gradeLevel: $data['grade_level'],
            gender: $data['gender'],
            city: $data['city'],
            state: $data['state'],
            zipCode: $data['zip_code'],
            password: $data['password'],
            scheduleEmail: $data['schedule_email'] ?? null,
        );
    }

    public function toCreateStudentDTO(Lead $lead): CreateStudentDTO
    {
        return new CreateStudentDTO(
            firstName: $lead->first_name,
            middleName: $lead->middle_name,
            lastName: $lead->last_name,
            username: $this->username,
            email: $this->email,
            gender: $this->gender,
            dateOfBirth: $lead->date_of_birth?->format('Y-m-d'),
            schoolId: $this->schoolId,
            idNumber: $this->idNumber,
            timezone: $this->timezone,
            gradeLevel: $this->gradeLevel,
            parentGuardianName: $lead->parent_guardian_name,
            parentGuardianEmail: $lead->parent_guardian_email,
            parentGuardianPhone: $lead->parent_guardian_phone,
            scheduleEmail: $this->scheduleEmail,
            parentGuardian2Name: null,
            parentGuardian2Email: null,
            parentGuardian2Phone: null,
            address: $lead->address,
            city: $this->city,
            state: $this->state,
            zipCode: $this->zipCode,
            password: $this->password,
        );
    }
}
