<?php

declare(strict_types=1);

use App\DTOs\CreateStudentProfileDTO;
use App\DTOs\UpdateStudentProfileDTO;

describe('CreateStudentProfileDTO', function () {
    it('can be created from array with all fields', function () {
        $data = [
            'user_id' => 1,
            'parent_id' => 2,
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'school' => 'Test School',
            'id_number' => 'STU123',
            'timezone' => 'America/New_York',
            'gender' => 'Male',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'parent_guardian_name' => 'Jane Doe',
            'parent_guardian_email' => 'jane@example.com',
            'parent_guardian_phone' => '555-1234',
            'date_of_birth' => '2010-01-01',
            'grade_level' => '5',
        ];

        $dto = CreateStudentProfileDTO::fromArray($data);

        expect($dto->userId)->toBe(1);
        expect($dto->parentId)->toBe(2);
        expect($dto->firstName)->toBe('John');
        expect($dto->middleName)->toBe('Michael');
        expect($dto->lastName)->toBe('Doe');
        expect($dto->school)->toBe('Test School');
        expect($dto->idNumber)->toBe('STU123');
        expect($dto->timezone)->toBe('America/New_York');
        expect($dto->gender)->toBe('Male');
        expect($dto->address)->toBe('123 Main St');
        expect($dto->city)->toBe('New York');
        expect($dto->state)->toBe('NY');
        expect($dto->zipCode)->toBe('10001');
        expect($dto->parentGuardianName)->toBe('Jane Doe');
        expect($dto->parentGuardianEmail)->toBe('jane@example.com');
        expect($dto->parentGuardianPhone)->toBe('555-1234');
        expect($dto->dateOfBirth)->toBe('2010-01-01');
        expect($dto->gradeLevel)->toBe('5');
    });

    it('handles null values correctly', function () {
        $data = [
            'user_id' => 1,
        ];

        $dto = CreateStudentProfileDTO::fromArray($data);

        expect($dto->userId)->toBe(1);
        expect($dto->parentId)->toBeNull();
        expect($dto->firstName)->toBeNull();
        expect($dto->middleName)->toBeNull();
        expect($dto->lastName)->toBeNull();
    });

    it('can convert to array', function () {
        $data = [
            'user_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        $dto = CreateStudentProfileDTO::fromArray($data);
        $result = $dto->toArray();

        expect($result)->toBeArray();
        expect($result['user_id'])->toBe(1);
        expect($result['first_name'])->toBe('John');
        expect($result['last_name'])->toBe('Doe');
    });
});

describe('UpdateStudentProfileDTO', function () {
    it('can be created from array with all fields', function () {
        $data = [
            'parent_id' => 2,
            'first_name' => 'Jane',
            'middle_name' => 'Marie',
            'last_name' => 'Smith',
            'school' => 'Updated School',
            'id_number' => 'STU456',
            'timezone' => 'America/Los_Angeles',
            'gender' => 'Female',
            'address' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'zip_code' => '90001',
            'parent_guardian_name' => 'Bob Smith',
            'parent_guardian_email' => 'bob@example.com',
            'parent_guardian_phone' => '555-4321',
            'date_of_birth' => '2011-02-15',
            'grade_level' => '6',
        ];

        $dto = UpdateStudentProfileDTO::fromArray($data);

        expect($dto->parentId)->toBe(2);
        expect($dto->firstName)->toBe('Jane');
        expect($dto->middleName)->toBe('Marie');
        expect($dto->lastName)->toBe('Smith');
        expect($dto->school)->toBe('Updated School');
        expect($dto->gradeLevel)->toBe('6');
    });

    it('handles null values correctly', function () {
        $data = [];

        $dto = UpdateStudentProfileDTO::fromArray($data);

        expect($dto->parentId)->toBeNull();
        expect($dto->firstName)->toBeNull();
        expect($dto->middleName)->toBeNull();
        expect($dto->lastName)->toBeNull();
    });

    it('can convert to array', function () {
        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'grade_level' => '6',
        ];

        $dto = UpdateStudentProfileDTO::fromArray($data);
        $result = $dto->toArray();

        expect($result)->toBeArray();
        expect($result['first_name'])->toBe('Jane');
        expect($result['last_name'])->toBe('Smith');
        expect($result['grade_level'])->toBe('6');
    });
});
