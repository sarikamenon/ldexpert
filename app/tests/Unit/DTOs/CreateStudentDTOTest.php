<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateStudentDTO;
use PHPUnit\Framework\TestCase;

final class CreateStudentDTOTest extends TestCase
{
    public function test_from_array_populates_all_fields(): void
    {
        $data = [
            'first_name' => 'Ava',
            'middle_name' => 'Lee',
            'last_name' => 'Rivera',
            'username' => 'ava.rivera.stu123',
            'email' => 'ava@example.com',
            'gender' => 'Female',
            'date_of_birth' => '2012-03-05',
            'school_id' => 5,
            'id_number' => 'STU-123',
            'timezone' => 'America/New_York',
            'grade_level' => '6',
            'parent_guardian_name' => 'Maria Rivera',
            'parent_guardian_email' => 'maria@example.com',
            'parent_guardian_phone' => '123-456-7890',
            'schedule_email' => 'schedule@example.com',
            'parent_guardian_2_name' => 'Carlos Rivera',
            'parent_guardian_2_email' => 'carlos@example.com',
            'parent_guardian_2_phone' => '987-654-3210',
            'address' => '123 Elm St',
            'city' => 'Boston',
            'state' => 'MA',
            'zip_code' => '02115',
            'password' => 'SecurePass123!',
        ];

        $dto = CreateStudentDTO::fromArray($data);

        $this->assertSame('Ava', $dto->firstName);
        $this->assertSame('Lee', $dto->middleName);
        $this->assertSame('Rivera', $dto->lastName);
        $this->assertSame('ava.rivera.stu123', $dto->username);
        $this->assertSame('ava@example.com', $dto->email);
        $this->assertSame('Female', $dto->gender);
        $this->assertSame('2012-03-05', $dto->dateOfBirth);
        $this->assertSame(5, $dto->schoolId);
        $this->assertSame('STU-123', $dto->idNumber);
        $this->assertSame('America/New_York', $dto->timezone);
        $this->assertSame('6', $dto->gradeLevel);
        $this->assertSame('Maria Rivera', $dto->parentGuardianName);
        $this->assertSame('maria@example.com', $dto->parentGuardianEmail);
        $this->assertSame('123-456-7890', $dto->parentGuardianPhone);
        $this->assertSame('schedule@example.com', $dto->scheduleEmail);
        $this->assertSame('Carlos Rivera', $dto->parentGuardian2Name);
        $this->assertSame('carlos@example.com', $dto->parentGuardian2Email);
        $this->assertSame('987-654-3210', $dto->parentGuardian2Phone);
        $this->assertSame('123 Elm St', $dto->address);
        $this->assertSame('Boston', $dto->city);
        $this->assertSame('MA', $dto->state);
        $this->assertSame('02115', $dto->zipCode);
        $this->assertSame('SecurePass123!', $dto->password);
    }

    public function test_date_of_birth_empty_string_becomes_null(): void
    {
        $dto = CreateStudentDTO::fromArray([
            'first_name' => 'Sam',
            'last_name' => 'Test',
            'username' => 'sam.test',
            'email' => 'sam@example.com',
            'date_of_birth' => '',
            'timezone' => 'America/Chicago',
            'password' => 'TempPass!',
        ]);

        $this->assertNull($dto->dateOfBirth);
    }

    public function test_optional_fields_default_to_null(): void
    {
        $dto = CreateStudentDTO::fromArray([
            'first_name' => 'Eli',
            'last_name' => 'Stone',
            'username' => 'eli.stone',
            'email' => 'eli@example.com',
            'date_of_birth' => '2013-11-09',
            'timezone' => 'America/Chicago',
            'password' => 'TempPass!',
        ]);

        $this->assertNull($dto->middleName);
        $this->assertNull($dto->gender);
        $this->assertNull($dto->schoolId);
        $this->assertNull($dto->idNumber);
        $this->assertNull($dto->gradeLevel);
        $this->assertNull($dto->parentGuardianName);
        $this->assertNull($dto->parentGuardianEmail);
        $this->assertNull($dto->parentGuardianPhone);
        $this->assertNull($dto->scheduleEmail);
        $this->assertNull($dto->parentGuardian2Name);
        $this->assertNull($dto->parentGuardian2Email);
        $this->assertNull($dto->parentGuardian2Phone);
        $this->assertNull($dto->address);
        $this->assertNull($dto->city);
        $this->assertNull($dto->state);
        $this->assertNull($dto->zipCode);
    }

    public function test_to_user_array_returns_expected_payload(): void
    {
        $dto = new CreateStudentDTO(
            firstName: 'Avery',
            middleName: null,
            lastName: 'Woods',
            username: 'avery.woods',
            email: 'avery@example.com',
            gender: null,
            dateOfBirth: '2011-01-15',
            schoolId: null,
            idNumber: null,
            timezone: 'America/Denver',
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
            password: 'MyPass123!'
        );

        $userArray = $dto->toUserArray();

        $this->assertSame('Avery Woods', $userArray['name']);
        $this->assertSame('avery.woods', $userArray['username']);
        $this->assertSame('avery@example.com', $userArray['email']);
        $this->assertSame('MyPass123!', $userArray['password']);
        $this->assertSame('student', $userArray['role']);
        $this->assertSame('active', $userArray['status']);
    }

    public function test_to_profile_array_maps_fields_correctly(): void
    {
        $dto = new CreateStudentDTO(
            firstName: 'Mia',
            middleName: 'Rose',
            lastName: 'Nguyen',
            username: 'mia.nguyen.stu777',
            email: 'mia@example.com',
            gender: 'Female',
            dateOfBirth: '2010-07-21',
            schoolId: 7,
            idNumber: 'STU777',
            timezone: 'America/Los_Angeles',
            gradeLevel: '7',
            parentGuardianName: 'Lan Nguyen',
            parentGuardianEmail: 'lan@example.com',
            parentGuardianPhone: '222-333-4444',
            scheduleEmail: 'reminders@example.com',
            parentGuardian2Name: 'Binh Nguyen',
            parentGuardian2Email: 'binh@example.com',
            parentGuardian2Phone: '555-666-7777',
            address: '789 Maple Ave',
            city: 'Seattle',
            state: 'WA',
            zipCode: '98101',
            password: 'StudentPass!'
        );

        $profileArray = $dto->toProfileArray(42);

        $this->assertSame(42, $profileArray['user_id']);
        $this->assertSame('Mia', $profileArray['first_name']);
        $this->assertSame('Rose', $profileArray['middle_name']);
        $this->assertSame('Nguyen', $profileArray['last_name']);
        $this->assertSame(7, $profileArray['school_id']);
        $this->assertSame('STU777', $profileArray['id_number']);
        $this->assertSame('America/Los_Angeles', $profileArray['timezone']);
        $this->assertSame('Female', $profileArray['gender']);
        $this->assertSame('789 Maple Ave', $profileArray['address']);
        $this->assertSame('Seattle', $profileArray['city']);
        $this->assertSame('WA', $profileArray['state']);
        $this->assertSame('98101', $profileArray['zip_code']);
        $this->assertSame('Lan Nguyen', $profileArray['parent_guardian_name']);
        $this->assertSame('lan@example.com', $profileArray['parent_guardian_email']);
        $this->assertSame('222-333-4444', $profileArray['parent_guardian_phone']);
        $this->assertSame('reminders@example.com', $profileArray['schedule_email']);
        $this->assertSame('Binh Nguyen', $profileArray['parent_guardian_2_name']);
        $this->assertSame('binh@example.com', $profileArray['parent_guardian_2_email']);
        $this->assertSame('555-666-7777', $profileArray['parent_guardian_2_phone']);
        $this->assertSame('2010-07-21', $profileArray['date_of_birth']);
        $this->assertSame('7', $profileArray['grade_level']);
    }
}
