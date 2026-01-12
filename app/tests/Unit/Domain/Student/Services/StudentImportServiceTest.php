<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\Services\StudentImportService;
use App\Domain\Student\Services\StudentService;
use App\DTOs\ImportStudentDTO;
use App\DTOs\ImportStudentResultDTO;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class StudentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $service;

    private StudentRepositoryInterface $repository;

    private StudentService $studentService;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(StudentRepositoryInterface::class);
        $this->studentService = $this->app->make(StudentService::class);
        $this->service = new StudentImportService($this->repository, $this->studentService);
        $this->school = School::factory()->create();
    }

    public function test_validate_file_structure_with_valid_headers(): void
    {
        $csvContent = "first_name,last_name,email,gender,date_of_birth,school_id,id_number,timezone,grade_level,city,state,zip_code\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $template = $this->service->getTemplate();
        $errors = $this->service->validateFileStructure($file, $template);

        $this->assertEmpty($errors);
    }

    public function test_validate_file_structure_with_missing_required_columns(): void
    {
        $csvContent = "first_name,last_name,email\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $template = $this->service->getTemplate();
        $errors = $this->service->validateFileStructure($file, $template);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Missing required columns', $errors[0]);
    }

    public function test_parse_csv_returns_correct_data(): void
    {
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $rows = $this->service->parseCsv($file);

        $this->assertCount(1, $rows);
        $this->assertEquals('John', $rows[0]['first_name']);
        $this->assertEquals('Doe', $rows[0]['last_name']);
        $this->assertEquals('john@example.com', $rows[0]['email']);
    }

    public function test_parse_csv_skips_empty_rows(): void
    {
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\n\n\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $rows = $this->service->parseCsv($file);

        $this->assertCount(1, $rows);
    }

    public function test_map_columns_maps_data_correctly(): void
    {
        $rowData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ];

        $template = $this->service->getTemplate();
        $mapped = $this->service->mapColumns($rowData, $template, $this->school->id);

        $this->assertEquals('Jane', $mapped['first_name']);
        $this->assertEquals('Smith', $mapped['last_name']);
        $this->assertEquals('jane@example.com', $mapped['email']);
        $this->assertEquals($this->school->id, $mapped['school_id']);
    }

    public function test_check_duplicate_by_email_returns_reason(): void
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        StudentProfile::factory()->create(['user_id' => $existingUser->id]);

        $data = [
            'email' => 'existing@example.com',
            'id_number' => 'STU001',
        ];

        $reason = $this->service->checkDuplicate($data, $this->school->id);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('email', $reason);
    }

    public function test_check_duplicate_by_id_number_returns_reason(): void
    {
        $existingUser = User::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $existingUser->id,
            'school_id' => $this->school->id,
            'id_number' => 'STU002',
        ]);

        $data = [
            'email' => 'new@example.com',
            'id_number' => 'STU002',
        ];

        $reason = $this->service->checkDuplicate($data, $this->school->id);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('ID number', $reason);
    }

    public function test_check_duplicate_returns_null_when_no_duplicate(): void
    {
        $data = [
            'email' => 'new@example.com',
            'id_number' => 'STU003',
        ];

        $reason = $this->service->checkDuplicate($data, $this->school->id);

        $this->assertNull($reason);
    }

    public function test_validate_row_with_valid_data_returns_empty_errors(): void
    {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2010-01-01',
            'school_id' => $this->school->id,
            'id_number' => 'STU004',
            'timezone' => 'America/New_York',
            'grade_level' => '8',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
        ];

        $dto = ImportStudentDTO::fromArray($data, 1);
        $errors = $this->service->validateRow($dto, $this->school->id);

        $this->assertEmpty($errors);
    }

    public function test_validate_row_with_invalid_timezone_returns_error(): void
    {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2010-01-01',
            'school_id' => $this->school->id,
            'id_number' => 'STU005',
            'timezone' => 'Invalid/Timezone',
            'grade_level' => '8',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
        ];

        $dto = ImportStudentDTO::fromArray($data, 1);
        $errors = $this->service->validateRow($dto, $this->school->id);

        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid timezone', $errors);
    }

    public function test_validate_row_with_invalid_state_returns_error(): void
    {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2010-01-01',
            'school_id' => $this->school->id,
            'id_number' => 'STU006',
            'timezone' => 'America/New_York',
            'grade_level' => '8',
            'city' => 'New York',
            'state' => 'XX',
            'zip_code' => '10001',
        ];

        $dto = ImportStudentDTO::fromArray($data, 1);
        $errors = $this->service->validateRow($dto, $this->school->id);

        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid state', $errors);
    }

    public function test_get_template_returns_default_when_no_school_specific(): void
    {
        $template = $this->service->getTemplate($this->school->id);

        $this->assertIsArray($template);
        $this->assertArrayHasKey('required_columns', $template);
        $this->assertArrayHasKey('optional_columns', $template);
        $this->assertArrayHasKey('column_mapping', $template);
    }
}
