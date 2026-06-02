<?php

declare(strict_types=1);

use App\DTOs\ConvertLeadDTO;
use App\Enums\SchoolType;
use App\Models\Lead;

it('maps lead parent fields and leaves the second guardian null', function () {
    $lead = new Lead([
        'first_name' => 'Ava',
        'middle_name' => null,
        'last_name' => 'Rivera',
        'parent_guardian_name' => 'Maria Rivera',
        'parent_guardian_email' => 'maria@example.com',
        'parent_guardian_phone' => '123-456-7890',
        'address' => '123 Elm St',
    ]);

    $dto = new ConvertLeadDTO(
        username: 'ava.rivera',
        email: 'ava@example.com',
        timezone: 'America/New_York',
        password: 'SecurePass123!',
        managerId: 1,
        schoolId: 5,
        idNumber: 'STU-1',
        gradeLevel: '6',
        gender: 'Female',
        city: 'Boston',
        state: 'MA',
        zipCode: '02115',
        scheduleEmail: 'schedule@example.com',
    );

    $student = $dto->toCreateStudentDTO($lead);

    expect($student->parentGuardianName)->toBe('Maria Rivera')
        ->and($student->parentGuardianEmail)->toBe('maria@example.com')
        ->and($student->parentGuardianPhone)->toBe('123-456-7890')
        ->and($student->parentGuardian2Name)->toBeNull()
        ->and($student->parentGuardian2Email)->toBeNull()
        ->and($student->parentGuardian2Phone)->toBeNull()
        ->and($student->schoolId)->toBe(5);
});

it('overrides the student school id with the created family id', function () {
    $lead = new Lead(['first_name' => 'Ava', 'last_name' => 'Rivera']);

    $dto = new ConvertLeadDTO(
        username: 'ava.rivera',
        email: 'ava@example.com',
        timezone: 'America/New_York',
        password: 'SecurePass123!',
        managerId: 1,
        schoolId: null,
        createPrivateFamily: true,
    );

    $student = $dto->toCreateStudentDTO($lead, 99);

    expect($student->schoolId)->toBe(99);
});

it('builds a private-family school DTO from the family_* values and forces private', function () {
    $dto = new ConvertLeadDTO(
        username: 'ava.rivera',
        email: 'ava@example.com',
        timezone: 'America/New_York',
        password: 'SecurePass123!',
        managerId: 7,
        schoolId: null,
        createPrivateFamily: true,
        familyFullName: 'Rivera Family Official',
        familyName: 'Rivera Family',
        familySchoolType: SchoolType::VIRTUAL->value,
        familyState: 'MA',
        familyTimezone: 'America/New_York',
        familyAddress: '123 Elm St',
        familyContactFirstName: 'Maria',
        familyContactLastName: 'Rivera',
        familyContactEmail: 'maria@example.com',
        familyContactPhone: '123-456-7890',
        familyIsAutoExtend: true,
    );

    $school = $dto->toCreateSchoolDTO();

    expect($school->fullName)->toBe('Rivera Family Official')
        ->and($school->displayName)->toBe('Rivera Family')
        ->and($school->schoolType)->toBe(SchoolType::VIRTUAL->value)
        ->and($school->state)->toBe('MA')
        ->and($school->timezone)->toBe('America/New_York')
        ->and($school->managerId)->toBe(7)
        ->and($school->isPrivateStudent)->toBeTrue()
        ->and($school->isAutoExtend)->toBeTrue()
        ->and($school->contactFirstName)->toBe('Maria');
});

it('creates a non-private school DTO when the private-family box is not checked', function () {
    $dto = new ConvertLeadDTO(
        username: 'ava.rivera',
        email: 'ava@example.com',
        timezone: 'America/New_York',
        password: 'SecurePass123!',
        managerId: 1,
        schoolId: null,
        createPrivateFamily: false,
        familyName: 'North Ridge Academy',
        familySchoolType: SchoolType::BRICK_MORTAR->value,
        familyState: 'MA',
        familyTimezone: 'America/New_York',
    );

    $school = $dto->toCreateSchoolDTO();

    expect($school->isPrivateStudent)->toBeFalse()
        ->and($school->schoolType)->toBe(SchoolType::BRICK_MORTAR->value);
});

it('defaults the family school type to Virtual when not provided', function () {
    $dto = new ConvertLeadDTO(
        username: 'ava.rivera',
        email: 'ava@example.com',
        timezone: 'America/New_York',
        password: 'SecurePass123!',
        managerId: 1,
        createPrivateFamily: true,
        familyName: 'Rivera Family',
        familyState: 'MA',
        familyTimezone: 'America/New_York',
    );

    expect($dto->toCreateSchoolDTO()->schoolType)->toBe(SchoolType::VIRTUAL->value);
});
