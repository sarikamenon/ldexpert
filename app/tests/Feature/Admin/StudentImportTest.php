<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\StudentImportStatus;
use App\Enums\StudentImportType;
use App\Jobs\ProcessStudentImportJob;
use App\Models\School;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->school = School::factory()->create([
            'external_emr_name' => 'Test School EMR',
        ]);

        $disk = config('filesystems.default');
        Storage::fake($disk);
        Bus::fake();
    }

    public function test_admin_can_view_import_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.import'));

        $response->assertOk()
            ->assertViewIs('admin.students.import')
            ->assertViewHas('requiredColumns')
            ->assertViewHas('optionalColumns')
            ->assertViewHas('importTypes');
    }

    public function test_non_admin_cannot_view_import_form(): void
    {
        $response = $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.students.import'));

        $response->assertForbidden();
    }

    public function test_admin_can_download_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.import.template'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('student-import-template-');
    }

    public function test_admin_can_import_valid_csv(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'gender' => 'Male',
                'date_of_birth' => '2010-05-15',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU001',
                'timezone' => 'America/New_York',
                'grade_level' => '8',
                'city' => 'New York',
                'state' => 'NY',
                'zip_code' => '10001',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'status',
                ],
            ]);

        Bus::assertDispatched(ProcessStudentImportJob::class);

        $import = StudentImport::first();
        $this->assertNotNull($import);

        // Run job synchronously to validate processing
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => Role::STUDENT->value,
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'id_number' => 'STU001',
            'school_id' => $this->school->id,
        ]);

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('done', $row->status->value);
    }

    public function test_import_validates_required_columns(): void
    {
        $csvContent = "first_name,last_name\nJohn,Doe\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessStudentImportJob::class);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));
        $import->refresh();

        $this->assertEquals(StudentImportStatus::FAILED, $import->status);
        $this->assertStringContainsString('Missing required columns', (string) $import->error_message);
    }

    public function test_import_skips_duplicate_by_email(): void
    {
        Mail::fake();

        // Create existing student
        $existingStudent = User::factory()
            ->create([
                'email' => 'existing@example.com',
                'role' => Role::STUDENT->value,
            ]);

        StudentProfile::factory()->create([
            'user_id' => $existingStudent->id,
            'school_id' => $this->school->id,
        ]);

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'New',
                'last_name' => 'Student',
                'email' => 'existing@example.com',
                'gender' => 'Female',
                'date_of_birth' => '2011-06-20',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU002',
                'timezone' => 'America/Chicago',
                'grade_level' => '7',
                'city' => 'Chicago',
                'state' => 'IL',
                'zip_code' => '60601',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessStudentImportJob::class);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertDatabaseCount('users', 2); // admin + existing student

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('duplicate', $row->status->value);
    }

    public function test_import_skips_duplicate_by_id_number(): void
    {
        Mail::fake();

        // Create existing student with same id_number
        $existingStudent = User::factory()
            ->create([
                'role' => Role::STUDENT->value,
            ]);

        StudentProfile::factory()->create([
            'user_id' => $existingStudent->id,
            'school_id' => $this->school->id,
            'id_number' => 'STU003',
        ]);

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'Another',
                'last_name' => 'Student',
                'email' => 'another@example.com',
                'gender' => 'Male',
                'date_of_birth' => '2012-07-25',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU003',
                'timezone' => 'America/Denver',
                'grade_level' => '6',
                'city' => 'Denver',
                'state' => 'CO',
                'zip_code' => '80201',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessStudentImportJob::class);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertDatabaseCount('users', 2); // admin + existing student

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('duplicate', $row->status->value);
    }

    public function test_import_handles_multiple_rows(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice@example.com',
                'gender' => 'Female',
                'date_of_birth' => '2010-04-10',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU004',
                'timezone' => 'America/New_York',
                'grade_level' => '8',
                'city' => 'Boston',
                'state' => 'MA',
                'zip_code' => '02101',
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Jones',
                'email' => 'bob@example.com',
                'gender' => 'Male',
                'date_of_birth' => '2011-03-15',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU005',
                'timezone' => 'America/Chicago',
                'grade_level' => '7',
                'city' => 'Chicago',
                'state' => 'IL',
                'zip_code' => '60601',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessStudentImportJob::class);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);
    }

    private function generateCsvContent(array $rows): string
    {
        $requiredColumns = [
            'first_name',
            'last_name',
            'email',
            'gender',
            'date_of_birth',
            'school_name',
            'id_number',
            'timezone',
            'grade_level',
            'city',
            'state',
            'zip_code',
        ];

        $optionalColumns = [
            'middle_name',
            'address',
            'parent_guardian_name',
            'parent_guardian_email',
            'parent_guardian_phone',
        ];

        $allColumns = array_merge($requiredColumns, $optionalColumns);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $allColumns);

        foreach ($rows as $row) {
            $line = [];
            foreach ($allColumns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
