<?php

declare(strict_types=1);

use App\Models\User;

describe('Student Profile Fields', function () {
    it('creates student with complete profile information', function () {
        $therapist = User::factory()->therapist()->create();
        $this->actingAs($therapist);

        $studentData = [
            'name' => 'John Michael Doe',
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'school' => 'Lincoln Elementary',
            'id_number' => 'STU12345',
            'timezone' => 'America/New_York',
            'gender' => 'Male',
            'address' => '123 Main Street',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip_code' => '62701',
            'parent_guardian_name' => 'Jane Doe',
            'parent_guardian_email' => 'jane.doe@example.com',
            'parent_guardian_phone' => '555-123-4567',
            'date_of_birth' => '2015-03-15',
            'grade_level' => 'K',
        ];

        $response = $this->post('/therapist/students', $studentData);

        $response->assertRedirect();

        $student = User::where('email', 'john.doe@example.com')->first();
        expect($student)->not->toBeNull();
        expect($student->studentProfile)->not->toBeNull();

        $profile = $student->studentProfile;
        expect($student->name)->toBe('John Michael Doe');
        expect($profile->first_name)->toBe('John');
        expect($profile->middle_name)->toBe('Michael');
        expect($profile->last_name)->toBe('Doe');
        expect($profile->school)->toBe('Lincoln Elementary');
        expect($profile->id_number)->toBe('STU12345');
        expect($profile->timezone)->toBe('America/New_York');
        expect($profile->gender)->toBe('Male');
        expect($profile->address)->toBe('123 Main Street');
        expect($profile->city)->toBe('Springfield');
        expect($profile->state)->toBe('IL');
        expect($profile->zip_code)->toBe('62701');
        expect($profile->parent_guardian_name)->toBe('Jane Doe');
        expect($profile->parent_guardian_email)->toBe('jane.doe@example.com');
        expect($profile->parent_guardian_phone)->toBe('555-123-4567');
        expect($profile->date_of_birth->format('Y-m-d'))->toBe('2015-03-15');
        expect($profile->grade_level)->toBe('K');
    });

    it('updates student profile with new information', function () {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $therapist->students()->attach($student->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($therapist);

        $updateData = [
            'name' => 'Sarah Jane Smith',
            'email' => 'sarah.smith@example.com',
            'first_name' => 'Sarah',
            'middle_name' => 'Jane',
            'last_name' => 'Smith',
            'school' => 'Washington High',
            'id_number' => 'STU67890',
            'timezone' => 'America/Los_Angeles',
            'gender' => 'Female',
            'address' => '456 Oak Avenue',
            'city' => 'Portland',
            'state' => 'OR',
            'zip_code' => '97201',
            'parent_guardian_name' => 'Robert Smith',
            'parent_guardian_email' => 'robert.smith@example.com',
            'parent_guardian_phone' => '555-999-8888',
            'date_of_birth' => '2014-07-20',
            'grade_level' => '2',
            'parent_id' => null,
        ];

        $response = $this->patch("/therapist/students/{$student->id}", $updateData);

        $response->assertRedirect();

        $student->refresh();
        $profile = $student->studentProfile;

        expect($student->name)->toBe('Sarah Jane Smith');
        expect($student->email)->toBe('sarah.smith@example.com');
        expect($profile->first_name)->toBe('Sarah');
        expect($profile->middle_name)->toBe('Jane');
        expect($profile->last_name)->toBe('Smith');
        expect($profile->school)->toBe('Washington High');
        expect($profile->id_number)->toBe('STU67890');
        expect($profile->timezone)->toBe('America/Los_Angeles');
        expect($profile->gender)->toBe('Female');
        expect($profile->grade_level)->toBe('2');
    });

    it('creates student with minimal required fields', function () {
        $therapist = User::factory()->therapist()->create();
        $this->actingAs($therapist);

        $minimalData = [
            'name' => 'Minimal Student',
            'email' => 'minimal@example.com',
            'first_name' => 'Minimal',
            'last_name' => 'Student',
        ];

        $response = $this->post('/therapist/students', $minimalData);

        $response->assertRedirect();

        $student = User::where('email', 'minimal@example.com')->first();
        expect($student)->not->toBeNull();
        expect($student->studentProfile)->not->toBeNull();
        expect($student->studentProfile->first_name)->toBe('Minimal');
        expect($student->studentProfile->last_name)->toBe('Student');
    });

    it('validates student profile fields correctly', function () {
        $therapist = User::factory()->therapist()->create();
        $this->actingAs($therapist);

        // Missing required fields
        $invalidData = [
            'name' => 'Test Student',
            // Missing email, first_name, last_name
        ];

        $response = $this->post('/therapist/students', $invalidData);

        $response->assertSessionHasErrors(['email', 'first_name', 'last_name']);
    });

    it('handles full name generation correctly', function () {
        $therapist = User::factory()->therapist()->create();
        $this->actingAs($therapist);

        $testCases = [
            [
                'first_name' => 'John',
                'middle_name' => 'Michael',
                'last_name' => 'Doe',
                'expected' => 'John Michael Doe',
            ],
            [
                'first_name' => 'Jane',
                'middle_name' => '',
                'last_name' => 'Smith',
                'expected' => 'Jane Smith',
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'expected' => 'Bob Johnson',
            ],
        ];

        foreach ($testCases as $index => $case) {
            $data = [
                'name' => 'Test Student',
                'email' => "test{$index}@example.com",
                'first_name' => $case['first_name'],
                'middle_name' => $case['middle_name'] ?? '',
                'last_name' => $case['last_name'],
            ];

            $response = $this->post('/therapist/students', $data);
            $response->assertRedirect();

            $student = User::where('email', "test{$index}@example.com")->first();
            expect($student->name)->toBe($case['expected']);
        }
    });
});
