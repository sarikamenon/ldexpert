<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\UpdateStudentDTO;
use PHPUnit\Framework\TestCase;

final class UpdateStudentDTOTest extends TestCase
{
    public function test_from_array_populates_properties(): void
    {
        $data = [
            'first_name' => 'Liam',
            'middle_name' => 'J',
            'last_name' => 'Park',
            'username' => 'liam.park',
            'email' => 'liam@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2011-09-09',
            'school_id' => 3,
            'id_number' => 'ID-99',
            'timezone' => 'America/Chicago',
            'grade_level' => '5',
            'parent_guardian_name' => 'Andy Park',
            'parent_guardian_email' => 'andy@example.com',
            'parent_guardian_phone' => '555-222-1111',
            'schedule_email' => 'schedules@example.com',
            'parent_guardian_2_name' => 'Jamie Park',
            'parent_guardian_2_email' => 'jamie@example.com',
            'parent_guardian_2_phone' => '555-333-2222',
            'address' => '22 River Rd',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '73301',
        ];

        $dto = UpdateStudentDTO::fromArray($data);

        $this->assertSame('Liam', $dto->firstName);
        $this->assertSame('J', $dto->middleName);
        $this->assertSame('Park', $dto->lastName);
        $this->assertSame('liam.park', $dto->username);
        $this->assertSame('liam@example.com', $dto->email);
        $this->assertSame('Male', $dto->gender);
        $this->assertSame('2011-09-09', $dto->dateOfBirth);
        $this->assertSame(3, $dto->schoolId);
        $this->assertSame('ID-99', $dto->idNumber);
        $this->assertSame('America/Chicago', $dto->timezone);
        $this->assertSame('5', $dto->gradeLevel);
        $this->assertSame('Andy Park', $dto->parentGuardianName);
        $this->assertSame('andy@example.com', $dto->parentGuardianEmail);
        $this->assertSame('555-222-1111', $dto->parentGuardianPhone);
        $this->assertSame('schedules@example.com', $dto->scheduleEmail);
        $this->assertSame('Jamie Park', $dto->parentGuardian2Name);
        $this->assertSame('jamie@example.com', $dto->parentGuardian2Email);
        $this->assertSame('555-333-2222', $dto->parentGuardian2Phone);
        $this->assertSame('22 River Rd', $dto->address);
        $this->assertSame('Austin', $dto->city);
        $this->assertSame('TX', $dto->state);
        $this->assertSame('73301', $dto->zipCode);
    }

    public function test_to_user_array_returns_expected_payload(): void
    {
        $dto = new UpdateStudentDTO(
            firstName: 'Olivia',
            middleName: null,
            lastName: 'Chen',
            username: 'olivia.chen',
            email: 'olivia@example.com',
            gender: 'Female',
            dateOfBirth: '2013-12-01',
            schoolId: null,
            idNumber: null,
            timezone: 'America/Los_Angeles',
            gradeLevel: null,
            parentGuardianName: null,
            parentGuardianEmail: null,
            parentGuardianPhone: null,
            scheduleEmail: null,
            parentGuardian2Name: null,
            parentGuardian2Email: null,
            parentGuardian2Phone: null,
            address: null,
            city: null,
            state: null,
            zipCode: null,
        );

        $userArray = $dto->toUserArray();

        $this->assertSame('Olivia Chen', $userArray['name']);
        $this->assertSame('olivia.chen', $userArray['username']);
        $this->assertSame('olivia@example.com', $userArray['email']);
    }

    public function test_to_profile_array_maps_fields(): void
    {
        $dto = new UpdateStudentDTO(
            firstName: 'Noah',
            middleName: 'A',
            lastName: 'Rivera',
            username: 'noah.rivera',
            email: 'noah@example.com',
            gender: 'Male',
            dateOfBirth: '2012-02-14',
            schoolId: 9,
            idNumber: 'STU-900',
            timezone: 'America/New_York',
            gradeLevel: '7',
            parentGuardianName: 'Laura Rivera',
            parentGuardianEmail: 'laura@example.com',
            parentGuardianPhone: '321-654-0987',
            scheduleEmail: 'reminders@example.com',
            parentGuardian2Name: 'Diego Rivera',
            parentGuardian2Email: 'diego@example.com',
            parentGuardian2Phone: '444-222-1111',
            address: '999 Pine Ln',
            city: 'Miami',
            state: 'FL',
            zipCode: '33101',
        );

        $profile = $dto->toProfileArray();

        $this->assertSame('Noah', $profile['first_name']);
        $this->assertSame('A', $profile['middle_name']);
        $this->assertSame('Rivera', $profile['last_name']);
        $this->assertSame(9, $profile['school_id']);
        $this->assertSame('STU-900', $profile['id_number']);
        $this->assertSame('America/New_York', $profile['timezone']);
        $this->assertSame('Male', $profile['gender']);
        $this->assertSame('999 Pine Ln', $profile['address']);
        $this->assertSame('Miami', $profile['city']);
        $this->assertSame('FL', $profile['state']);
        $this->assertSame('33101', $profile['zip_code']);
        $this->assertSame('Laura Rivera', $profile['parent_guardian_name']);
        $this->assertSame('laura@example.com', $profile['parent_guardian_email']);
        $this->assertSame('321-654-0987', $profile['parent_guardian_phone']);
        $this->assertSame('reminders@example.com', $profile['schedule_email']);
        $this->assertSame('Diego Rivera', $profile['parent_guardian_2_name']);
        $this->assertSame('diego@example.com', $profile['parent_guardian_2_email']);
        $this->assertSame('444-222-1111', $profile['parent_guardian_2_phone']);
        $this->assertSame('2012-02-14', $profile['date_of_birth']);
        $this->assertSame('7', $profile['grade_level']);
    }
}
