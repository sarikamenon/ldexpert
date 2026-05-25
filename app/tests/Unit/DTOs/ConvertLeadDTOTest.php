<?php

declare(strict_types=1);

use App\DTOs\ConvertLeadDTO;
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
        schoolId: 5,
        idNumber: 'STU-1',
        timezone: 'America/New_York',
        gradeLevel: '6',
        gender: 'Female',
        city: 'Boston',
        state: 'MA',
        zipCode: '02115',
        password: 'SecurePass123!',
        scheduleEmail: 'schedule@example.com',
    );

    $student = $dto->toCreateStudentDTO($lead);

    expect($student->parentGuardianName)->toBe('Maria Rivera')
        ->and($student->parentGuardianEmail)->toBe('maria@example.com')
        ->and($student->parentGuardianPhone)->toBe('123-456-7890')
        ->and($student->parentGuardian2Name)->toBeNull()
        ->and($student->parentGuardian2Email)->toBeNull()
        ->and($student->parentGuardian2Phone)->toBeNull();
});
