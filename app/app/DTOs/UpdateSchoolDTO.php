<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdateSchoolDTO
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $displayName,
        public readonly ?string $address,
        public readonly string $state,
        public readonly string $timezone,
        public readonly int $managerId,
        public readonly ?string $contactFirstName,
        public readonly ?string $contactLastName,
        public readonly ?string $contactPhone,
        public readonly ?string $contactEmail,
        public readonly ?string $invoiceEmail,
        public readonly string $schoolType,
        public readonly bool $isPrivateStudent,
        public readonly bool $nonBillableScheduling,
        public readonly ?string $externalEmrName,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fullName: $data['full_name'],
            displayName: $data['display_name'],
            address: $data['address'] ?? null,
            state: $data['state'],
            timezone: $data['timezone'],
            managerId: (int) $data['manager_id'],
            contactFirstName: $data['contact_first_name'] ?? null,
            contactLastName: $data['contact_last_name'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
            contactEmail: $data['contact_email'] ?? null,
            invoiceEmail: $data['invoice_email'] ?? null,
            schoolType: $data['school_type'],
            isPrivateStudent: (bool) ($data['is_private_student'] ?? false),
            nonBillableScheduling: (bool) ($data['non_billable_scheduling'] ?? false),
            externalEmrName: $data['external_emr_name'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName,
            'display_name' => $this->displayName,
            'address' => $this->address,
            'state' => $this->state,
            'timezone' => $this->timezone,
            'manager_id' => $this->managerId,
            'contact_first_name' => $this->contactFirstName,
            'contact_last_name' => $this->contactLastName,
            'contact_phone' => $this->contactPhone,
            'contact_email' => $this->contactEmail,
            'invoice_email' => $this->invoiceEmail,
            'school_type' => $this->schoolType,
            'is_private_student' => $this->isPrivateStudent,
            'non_billable_scheduling' => $this->nonBillableScheduling,
            'external_emr_name' => $this->externalEmrName,
        ];
    }
}
