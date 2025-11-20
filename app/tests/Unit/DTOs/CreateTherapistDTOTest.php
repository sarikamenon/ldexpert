<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateTherapistDTO;
use PHPUnit\Framework\TestCase;

final class CreateTherapistDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_all_fields(): void
    {
        $data = [
            'employee_type' => 'W2',
            'title' => 'Dr.',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'personal_email' => 'john.doe@example.com',
            'phone' => '123-456-7890',
            'ld_email' => 'john.doe@ldexpert.com',
            'address' => '123 Main St',
            'comments' => 'Test comment',
            'position' => 'SLP',
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => 1,
            'max_weekly_hours' => 30,
            'dob' => '1990-01-01',
            'password' => 'SecurePass123!',
        ];

        $dto = CreateTherapistDTO::fromArray($data);

        $this->assertSame('W2', $dto->employeeType);
        $this->assertSame('Dr.', $dto->title);
        $this->assertSame('John', $dto->firstName);
        $this->assertSame('Doe', $dto->lastName);
        $this->assertSame('john.doe@example.com', $dto->personalEmail);
        $this->assertSame('123-456-7890', $dto->phone);
        $this->assertSame('john.doe@ldexpert.com', $dto->ldEmail);
        $this->assertSame('123 Main St', $dto->address);
        $this->assertSame('Test comment', $dto->comments);
        $this->assertSame('SLP', $dto->position);
        $this->assertSame('CA', $dto->state);
        $this->assertSame('America/Los_Angeles', $dto->timezone);
        $this->assertSame(1, $dto->managerId);
        $this->assertSame(30, $dto->maxWeeklyHours);
        $this->assertSame('1990-01-01', $dto->dob);
        $this->assertSame('SecurePass123!', $dto->password);
    }

    public function test_from_array_handles_optional_fields(): void
    {
        $data = [
            'employee_type' => '1099',
            'title' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'personal_email' => 'john.doe@example.com',
            'phone' => '123-456-7890',
            'position' => 'OT',
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => 1,
            'max_weekly_hours' => 25,
            'password' => 'Pass123!',
        ];

        $dto = CreateTherapistDTO::fromArray($data);

        $this->assertNull($dto->ldEmail);
        $this->assertNull($dto->address);
        $this->assertNull($dto->comments);
        $this->assertNull($dto->dob);
    }

    public function test_to_user_array_returns_correct_format(): void
    {
        $dto = new CreateTherapistDTO(
            employeeType: 'W2',
            title: 'Dr.',
            firstName: 'Jane',
            lastName: 'Smith',
            personalEmail: 'jane.smith@example.com',
            phone: '987-654-3210',
            ldEmail: 'jane.smith@ldexpert.com',
            address: '456 Oak Ave',
            comments: 'Another comment',
            position: 'PT',
            state: 'NY',
            timezone: 'America/New_York',
            managerId: 2,
            maxWeeklyHours: 32,
            dob: '1985-05-15',
            password: 'TestPass456!'
        );

        $array = $dto->toUserArray();

        $this->assertSame('Jane Smith', $array['name']);
        $this->assertSame('jane.smith@example.com', $array['email']);
        $this->assertSame('TestPass456!', $array['password']);
        $this->assertSame('therapist', $array['role']);
        $this->assertSame('active', $array['status']);
    }

    public function test_to_profile_array_returns_correct_format(): void
    {
        $dto = new CreateTherapistDTO(
            employeeType: '1099',
            title: 'Ms.',
            firstName: 'Alice',
            lastName: 'Johnson',
            personalEmail: 'alice@example.com',
            phone: '555-555-5555',
            ldEmail: null,
            address: null,
            comments: null,
            position: 'BCBA',
            state: 'TX',
            timezone: 'America/Chicago',
            managerId: 3,
            maxWeeklyHours: 28,
            dob: null,
            password: 'Pass789!'
        );

        $array = $dto->toProfileArray(10);

        $this->assertSame(10, $array['user_id']);
        $this->assertSame('1099', $array['employee_type']);
        $this->assertSame('Ms.', $array['title']);
        $this->assertSame('Alice', $array['first_name']);
        $this->assertSame('Johnson', $array['last_name']);
        $this->assertSame('alice@example.com', $array['personal_email']);
        $this->assertSame('555-555-5555', $array['phone']);
        $this->assertNull($array['ld_email']);
        $this->assertNull($array['address']);
        $this->assertNull($array['comments']);
        $this->assertSame('BCBA', $array['position']);
        $this->assertSame('TX', $array['state']);
        $this->assertSame('America/Chicago', $array['timezone']);
        $this->assertSame(3, $array['manager_id']);
        $this->assertSame(28, $array['max_weekly_hours']);
        $this->assertNull($array['dob']);
    }
}
