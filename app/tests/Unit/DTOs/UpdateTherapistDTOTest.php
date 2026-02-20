<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\UpdateTherapistDTO;
use PHPUnit\Framework\TestCase;

final class UpdateTherapistDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_all_fields(): void
    {
        $data = [
            'employee_type' => '1099',
            'title' => 'Mrs.',
            'first_name' => 'Sarah',
            'last_name' => 'Williams',
            'personal_email' => 'sarah.w@example.com',
            'phone' => '222-333-4444',
            'ld_email' => 'sarah.w@ldexpert.com',
            'address' => '789 Elm St',
            'comments' => 'Updated comment',
            'position_id' => 4,
            'state' => 'FL',
            'timezone' => 'America/New_York',
            'manager_id' => 5,
            'max_weekly_hours' => 20,
            'dob' => '1988-03-20',
            'default_meeting_location' => 'https://meet.google.com/xyz',
        ];

        $dto = UpdateTherapistDTO::fromArray($data);

        $this->assertSame('1099', $dto->employeeType);
        $this->assertSame('Mrs.', $dto->title);
        $this->assertSame('Sarah', $dto->firstName);
        $this->assertSame('Williams', $dto->lastName);
        $this->assertSame('sarah.w@example.com', $dto->personalEmail);
        $this->assertSame('222-333-4444', $dto->phone);
        $this->assertSame('sarah.w@ldexpert.com', $dto->ldEmail);
        $this->assertSame('789 Elm St', $dto->address);
        $this->assertSame('Updated comment', $dto->comments);
        $this->assertSame(4, $dto->positionId);
        $this->assertSame('FL', $dto->state);
        $this->assertSame('America/New_York', $dto->timezone);
        $this->assertSame(5, $dto->managerId);
        $this->assertSame(20, $dto->maxWeeklyHours);
        $this->assertSame('1988-03-20', $dto->dob);
        $this->assertSame('https://meet.google.com/xyz', $dto->defaultMeetingLocation);
    }

    public function test_to_user_array_returns_correct_format(): void
    {
        $dto = new UpdateTherapistDTO(
            employeeType: 'W2',
            title: 'Mr.',
            firstName: 'Michael',
            lastName: 'Brown',
            personalEmail: 'michael.b@example.com',
            phone: '111-222-3333',
            ldEmail: 'michael.b@ldexpert.com',
            address: '321 Pine St',
            comments: 'New comment',
            positionId: 7,
            state: 'WA',
            timezone: 'America/Los_Angeles',
            managerId: 7,
            maxWeeklyHours: 36,
            dob: '1992-07-10',
            defaultMeetingLocation: null,
        );

        $array = $dto->toUserArray();

        $this->assertSame('Michael Brown', $array['name']);
        $this->assertSame('michael.b@example.com', $array['email']);
    }

    public function test_to_profile_array_returns_correct_format(): void
    {
        $dto = new UpdateTherapistDTO(
            employeeType: 'W2',
            title: 'Dr.',
            firstName: 'Emily',
            lastName: 'Davis',
            personalEmail: 'emily.d@example.com',
            phone: '444-555-6666',
            ldEmail: 'emily.d@ldexpert.com',
            address: '999 Main St',
            comments: 'Test',
            positionId: 1,
            state: 'CA',
            timezone: 'America/Los_Angeles',
            managerId: 1,
            maxWeeklyHours: 40,
            dob: '1990-01-01',
            defaultMeetingLocation: 'https://meet.google.com/test'
        );

        $array = $dto->toProfileArray();

        $this->assertSame('W2', $array['employee_type']);
        $this->assertSame('Dr.', $array['title']);
        $this->assertSame('Emily', $array['first_name']);
        $this->assertSame('Davis', $array['last_name']);
        $this->assertSame('emily.d@example.com', $array['personal_email']);
        $this->assertSame('444-555-6666', $array['phone']);
        $this->assertSame('emily.d@ldexpert.com', $array['ld_email']);
        $this->assertSame('999 Main St', $array['address']);
        $this->assertSame('Test', $array['comments']);
        $this->assertSame(1, $array['position_id']);
        $this->assertSame('CA', $array['state']);
        $this->assertSame('America/Los_Angeles', $array['timezone']);
        $this->assertSame(1, $array['manager_id']);
        $this->assertSame(40, $array['max_weekly_hours']);
        $this->assertSame('1990-01-01', $array['dob']);
        $this->assertSame('https://meet.google.com/test', $array['default_meeting_location']);
    }
}
