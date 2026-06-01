<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SchoolType;
use App\Models\Lead;

final class ConvertLeadDTO
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $timezone,
        public readonly string $password,
        public readonly int $managerId,
        public readonly ?int $schoolId = null,
        public readonly bool $createPrivateFamily = false,
        public readonly ?string $idNumber = null,
        public readonly ?string $gradeLevel = null,
        public readonly ?string $gender = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zipCode = null,
        public readonly ?string $scheduleEmail = null,
        public readonly ?string $familyName = null,
        public readonly ?string $familySchoolType = null,
        public readonly ?string $familyState = null,
        public readonly ?string $familyTimezone = null,
        public readonly ?string $familyAddress = null,
        public readonly ?string $familyContactFirstName = null,
        public readonly ?string $familyContactLastName = null,
        public readonly ?string $familyContactEmail = null,
        public readonly ?string $familyContactPhone = null,
        public readonly ?string $familyInvoiceEmail = null,
        public readonly bool $familyIsAutoExtend = false,
        public readonly bool $familyNonBillableScheduling = false,
        public readonly bool $familyAllowWeekendScheduling = false,
        public readonly ?string $familyExternalEmrName = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'],
            email: $data['email'],
            timezone: $data['timezone'],
            password: $data['password'],
            managerId: (int) $data['manager_id'],
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            createPrivateFamily: (bool) ($data['create_private_family'] ?? false),
            idNumber: $data['id_number'] ?? null,
            gradeLevel: $data['grade_level'] ?? null,
            gender: $data['gender'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            scheduleEmail: $data['schedule_email'] ?? null,
            familyName: $data['family_name'] ?? null,
            familySchoolType: $data['family_school_type'] ?? null,
            familyState: $data['family_state'] ?? null,
            familyTimezone: $data['family_timezone'] ?? null,
            familyAddress: $data['family_address'] ?? null,
            familyContactFirstName: $data['family_contact_first_name'] ?? null,
            familyContactLastName: $data['family_contact_last_name'] ?? null,
            familyContactEmail: $data['family_contact_email'] ?? null,
            familyContactPhone: $data['family_contact_phone'] ?? null,
            familyInvoiceEmail: $data['family_invoice_email'] ?? null,
            familyIsAutoExtend: (bool) ($data['family_is_auto_extend'] ?? false),
            familyNonBillableScheduling: (bool) ($data['family_non_billable_scheduling'] ?? false),
            familyAllowWeekendScheduling: (bool) ($data['family_allow_weekend_scheduling'] ?? false),
            familyExternalEmrName: $data['family_external_emr_name'] ?? null,
        );
    }

    public function toCreateStudentDTO(Lead $lead, ?int $schoolId = null): CreateStudentDTO
    {
        return new CreateStudentDTO(
            firstName: $lead->first_name,
            middleName: $lead->middle_name,
            lastName: $lead->last_name,
            username: $this->username,
            email: $this->email,
            gender: $this->gender,
            dateOfBirth: $lead->date_of_birth?->format('Y-m-d'),
            schoolId: $schoolId ?? $this->schoolId,
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

    /**
     * Build the new School from the admin-edited family_* values. Flagged as a private
     * family when the admin checked the box; otherwise a normal (non-private) school.
     */
    public function toCreateSchoolDTO(): CreateSchoolDTO
    {
        return new CreateSchoolDTO(
            fullName: (string) $this->familyName,
            displayName: (string) $this->familyName,
            address: $this->familyAddress,
            state: (string) $this->familyState,
            timezone: (string) $this->familyTimezone,
            managerId: $this->managerId,
            contactFirstName: $this->familyContactFirstName,
            contactLastName: $this->familyContactLastName,
            contactPhone: $this->familyContactPhone,
            contactEmail: $this->familyContactEmail,
            invoiceEmail: $this->familyInvoiceEmail,
            schoolType: $this->familySchoolType ?? SchoolType::VIRTUAL->value,
            isPrivateStudent: $this->createPrivateFamily,
            isAutoExtend: $this->familyIsAutoExtend,
            nonBillableScheduling: $this->familyNonBillableScheduling,
            allowWeekendScheduling: $this->familyAllowWeekendScheduling,
            externalEmrName: $this->familyExternalEmrName,
        );
    }
}
