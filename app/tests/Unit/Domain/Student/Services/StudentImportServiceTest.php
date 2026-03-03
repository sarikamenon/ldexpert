<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\Services\StudentImportService;
use App\Domain\Student\Services\StudentService;
use App\DTOs\ImportStudentDTO;
use App\Enums\StudentImportStatus;
use App\Enums\StudentImportType;
use App\Models\School;
use App\Models\StudentImport;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StudentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $service;

    private StudentRepositoryInterface $repository;

    private StudentService $studentService;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(StudentRepositoryInterface::class);
        $this->studentService = $this->app->make(StudentService::class);
        $this->service = $this->app->make(StudentImportService::class);

        $this->admin = User::factory()->admin()->create();
        $this->school = School::factory()->create([
            'external_emr_name' => 'Test School EMR',
        ]);

        $disk = config('filesystems.default');
        Storage::fake($disk);
    }

    public function test_validate_file_structure_with_valid_headers(): void
    {
        $csvContent = "first_name,last_name,email,gender,date_of_birth,school_name,id_number,timezone,grade_level,city,state,zip_code\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $path = 'student-imports/tests/valid-headers.csv';
        $disk = config('filesystems.default');
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $import = StudentImport::create([
            'user_id' => $this->admin->id,
            'type' => StudentImportType::NOVA,
            'file_path' => $path,
            'file_name' => 'students.csv',
            'total_rows' => 0,
            'processed_rows' => 0,
            'status' => StudentImportStatus::PENDING,
        ]);

        $template = $this->service->getTemplate(StudentImportType::NOVA);
        $errors = $this->service->validateFileStructure($import, $template);

        $this->assertEmpty($errors);
    }

    public function test_validate_file_structure_with_missing_required_columns(): void
    {
        $csvContent = "first_name,last_name,email\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $path = 'student-imports/tests/missing-columns.csv';
        $disk = config('filesystems.default');
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $import = StudentImport::create([
            'user_id' => $this->admin->id,
            'type' => StudentImportType::NOVA,
            'file_path' => $path,
            'file_name' => 'students.csv',
            'total_rows' => 0,
            'processed_rows' => 0,
            'status' => StudentImportStatus::PENDING,
        ]);

        $template = $this->service->getTemplate(StudentImportType::NOVA);
        $errors = $this->service->validateFileStructure($import, $template);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Missing required columns', $errors[0]);
    }

    public function test_parse_csv_returns_correct_data(): void
    {
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $path = 'student-imports/tests/parse-csv.csv';
        $disk = config('filesystems.default');
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $rows = $this->service->parseCsvFromStorage($path);

        $this->assertCount(1, $rows);
        $this->assertEquals('John', $rows[0]['first_name']);
        $this->assertEquals('Doe', $rows[0]['last_name']);
        $this->assertEquals('john@example.com', $rows[0]['email']);
    }

    public function test_parse_csv_skips_empty_rows(): void
    {
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\n\n\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $path = 'student-imports/tests/parse-csv-empty.csv';
        $disk = config('filesystems.default');
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $rows = $this->service->parseCsvFromStorage($path);

        $this->assertCount(1, $rows);
    }

    public function test_map_columns_maps_data_correctly(): void
    {
        $rowData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ];

        $template = $this->service->getTemplate(StudentImportType::NOVA);
        $mapped = $this->service->mapColumns($rowData, $template);

        $this->assertEquals('Jane', $mapped['first_name']);
        $this->assertEquals('Smith', $mapped['last_name']);
        $this->assertEquals('jane@example.com', $mapped['email']);
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

    public function test_validate_row_accepts_timezone_without_validation_error(): void
    {
        // Timezone validation removed: invalid/empty timezone is resolved via school fallback in processRow
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2010-01-01',
            'school_id' => $this->school->id,
            'id_number' => 'STU005',
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
        $this->assertContains('Invalid state.', $errors);
    }

    public function test_get_template_returns_default_when_no_school_specific(): void
    {
        $template = $this->service->getTemplate(StudentImportType::NOVA);

        $this->assertIsArray($template);
        $this->assertArrayHasKey('required_columns', $template);
        $this->assertArrayHasKey('optional_columns', $template);
        $this->assertArrayHasKey('column_mapping', $template);
    }

    public function test_get_template_returns_rsm_config(): void
    {
        $template = $this->service->getTemplate(StudentImportType::RSM);

        $this->assertIsArray($template);
        $this->assertArrayHasKey('required_columns', $template);
        $this->assertArrayHasKey('column_mapping', $template);
        $this->assertArrayHasKey('Identity ID', $template['column_mapping']);
        $this->assertArrayHasKey('field_sources', $template);
        $this->assertArrayHasKey('transformations', $template);
        $this->assertEquals('parent_guardian_email', $template['field_sources']['email']);
    }
}
