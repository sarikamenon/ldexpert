<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Student\Services\StudentService;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\CreateStudentDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\UpdateStudentDTO;
use App\Infrastructure\Repositories\EloquentStudentRepository;
use App\Mail\WelcomeStudentMail;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class StudentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StudentService(new EloquentStudentRepository());
    }

    public function test_create_creates_student_and_sends_welcome_email(): void
    {
        Mail::fake();
        $school = School::factory()->create();

        $dto = new CreateStudentDTO(
            firstName: 'Ava',
            middleName: null,
            lastName: 'Rivera',
            email: 'ava@example.com',
            gender: 'Female',
            dateOfBirth: '2012-03-05',
            schoolId: $school->id,
            idNumber: 'STU-101',
            timezone: 'America/New_York',
            gradeLevel: '6',
            parentGuardianName: 'Parent Name',
            parentGuardianEmail: 'parent@example.com',
            parentGuardianPhone: '123-456-7890',
            address: '123 School St',
            city: 'Boston',
            state: 'MA',
            zipCode: '02115',
            password: 'SecurePass123!'
        );

        $profile = $this->service->create($dto);

        $this->assertInstanceOf(StudentProfile::class, $profile);
        $this->assertSame('Ava', $profile->first_name);

        Mail::assertSent(WelcomeStudentMail::class, function (WelcomeStudentMail $mail) use ($dto) {
            return $mail->hasTo($dto->email);
        });
    }

    public function test_update_updates_student_profile(): void
    {
        $student = StudentProfile::factory()->create();
        $dto = new UpdateStudentDTO(
            firstName: 'Updated',
            middleName: null,
            lastName: 'Student',
            email: 'updated@example.com',
            gender: 'Male',
            dateOfBirth: '2011-04-04',
            schoolId: $student->school_id,
            idNumber: 'ID-22',
            timezone: 'America/Chicago',
            gradeLevel: '5',
            parentGuardianName: 'Guardian',
            parentGuardianEmail: 'guardian@example.com',
            parentGuardianPhone: '555-555-1212',
            address: '456 Updated Way',
            city: 'Austin',
            state: 'TX',
            zipCode: '73301'
        );

        $updated = $this->service->update($student->user, $dto);

        $this->assertSame('Updated', $updated->first_name);
        $this->assertSame('5', $updated->grade_level);
    }

    public function test_change_status_updates_user(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $dto = new ChangeStudentStatusDTO('inactive', 'Moved');

        $result = $this->service->changeStatus($student, $dto);

        $this->assertSame('inactive', $result->status->value);
    }

    public function test_list_returns_paginated_students(): void
    {
        $initialCount = User::where('role', 'student')->count();
        StudentProfile::factory()->count(3)->create();

        $paginator = $this->service->list(new StudentFilterDTO(null, null, 25));

        $this->assertEquals($initialCount + 3, $paginator->total());
    }

    public function test_get_metrics_returns_counts(): void
    {
        StudentProfile::factory()->count(2)->create();

        $metrics = $this->service->getMetrics();

        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('active', $metrics);
        $this->assertArrayHasKey('inactive', $metrics);
    }

    public function test_export_returns_collection(): void
    {
        $initialCount = User::where('role', 'student')->count();
        StudentProfile::factory()->count(2)->create();

        $collection = $this->service->export(new StudentFilterDTO(null, null, 100));

        $this->assertEquals($initialCount + 2, $collection->count());
    }

    public function test_find_returns_profile(): void
    {
        $profile = StudentProfile::factory()->create();

        $found = $this->service->find($profile->id);

        $this->assertInstanceOf(StudentProfile::class, $found);
        $this->assertSame($profile->id, $found->id);
    }
}
