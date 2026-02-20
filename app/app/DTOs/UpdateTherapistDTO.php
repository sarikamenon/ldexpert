<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdateTherapistDTO
{
    public function __construct(
        public readonly string $employeeType,
        public readonly string $title,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $personalEmail,
        public readonly string $phone,
        public readonly ?string $ldEmail,
        public readonly ?string $address,
        public readonly ?string $comments,
        public readonly int $positionId,
        public readonly string $state,
        public readonly string $timezone,
        public readonly int $managerId,
        public readonly int $maxWeeklyHours,
        public readonly float $hourlyRate,
        public readonly ?string $dob,
        public readonly ?string $defaultMeetingLocation,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeType: $data['employee_type'],
            title: $data['title'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            personalEmail: $data['personal_email'],
            phone: $data['phone'],
            ldEmail: $data['ld_email'] ?? null,
            address: $data['address'] ?? null,
            comments: $data['comments'] ?? null,
            positionId: (int) $data['position_id'],
            state: $data['state'],
            timezone: $data['timezone'],
            managerId: (int) $data['manager_id'],
            maxWeeklyHours: (int) $data['max_weekly_hours'],
            hourlyRate: (float) $data['hourly_rate'],
            dob: $data['dob'] ?? null,
            defaultMeetingLocation: $data['default_meeting_location'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toUserArray(): array
    {
        return [
            'name' => $this->firstName.' '.$this->lastName,
            'email' => $this->personalEmail,
        ];
    }

    /** @return array<string, mixed> */
    public function toProfileArray(): array
    {
        return [
            'employee_type' => $this->employeeType,
            'title' => $this->title,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'personal_email' => $this->personalEmail,
            'phone' => $this->phone,
            'ld_email' => $this->ldEmail,
            'address' => $this->address,
            'comments' => $this->comments,
            'position_id' => $this->positionId,
            'state' => $this->state,
            'timezone' => $this->timezone,
            'manager_id' => $this->managerId,
            'max_weekly_hours' => $this->maxWeeklyHours,
            'hourly_rate' => $this->hourlyRate,
            'dob' => $this->dob,
            'default_meeting_location' => $this->defaultMeetingLocation,
        ];
    }
}
