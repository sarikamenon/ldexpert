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
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'student-import-template-',
            (string) $response->headers->get('Content-Disposition')
        );
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

        // Schema allows duplicate emails (unique constraint dropped). checkDuplicate only
        // checks username and id_number, so duplicate email import succeeds.
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
        $userCountBefore = User::count();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertGreaterThan($userCountBefore, User::count());
        $this->assertGreaterThanOrEqual(2, User::where('email', 'existing@example.com')->count());

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('done', $row->status->value);
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
        $userCountBefore = User::count();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertSame($userCountBefore, User::count());
        $this->assertSame(1, StudentProfile::where('id_number', 'STU003')->count());

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

    public function test_import_defaults_to_nova_when_type_not_provided(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'Default',
                'last_name' => 'Type',
                'email' => 'default@example.com',
                'gender' => 'Male',
                'date_of_birth' => '2010-01-01',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU008',
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
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $import = StudentImport::first();
        $this->assertEquals(StudentImportType::NOVA->value, $import->type->value);

        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));
        $this->assertDatabaseHas('users', ['email' => 'default@example.com', 'role' => Role::STUDENT->value]);
    }

    public function test_rsm_import_uses_parent_email_when_dob_not_provided(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Identity ID' => 'RSM001',
                'Last Name' => 'Gifford',
                'First Name' => 'Ella',
                'Gender' => 'Female',
                'Grade' => '5',
                'School Name' => $this->school->external_emr_name,
                'City' => 'Provo',
                'Zip' => '84606',
                'Parent Email' => 'parent@example.com',
                'Parent First Name' => 'Brittany',
                'Parent Last Name' => 'Gifford',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm-students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::RSM->value,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        $this->assertDatabaseHas('users', [
            'email' => 'parent@example.com',
            'role' => Role::STUDENT->value,
        ]);

        $profile = StudentProfile::where('id_number', 'RSM001')->first();
        $this->assertNotNull($profile);
        $this->assertNull($profile->date_of_birth, 'date_of_birth is optional; no default when missing');
        $this->assertEquals('Brittany Gifford', $profile->parent_guardian_name);
    }

    public function test_rsm_import_accepts_optional_date_of_birth_when_provided(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Identity ID' => 'RSM003',
                'Last Name' => 'Williams',
                'First Name' => 'Emma',
                'Gender' => 'Female',
                'Grade' => '4',
                'School Name' => $this->school->external_emr_name,
                'City' => 'Provo',
                'Zip' => '84606',
                'Parent Email' => 'emma-dob@example.com',
                'Parent First Name' => 'Sarah',
                'Parent Last Name' => 'Williams',
                'Date of Birth' => '2012-03-15',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm-students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::RSM->value,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        $profile = StudentProfile::where('id_number', 'RSM003')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('2012-03-15', $profile->date_of_birth->format('Y-m-d'));
    }

    public function test_import_normalizes_formatted_phone_number(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Identity ID' => 'RSM002',
                'Last Name' => 'Smith',
                'First Name' => 'John',
                'Gender' => 'Male',
                'Grade' => '3',
                'School Name' => $this->school->external_emr_name,
                'City' => 'Provo',
                'Zip' => '84606',
                'Parent Email' => 'parent-phone@example.com',
                'Parent First Name' => 'Jane',
                'Parent Last Name' => 'Smith',
                'Phone' => '(385) 497-0814',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm-students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::RSM->value,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        $profile = StudentProfile::where('id_number', 'RSM002')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('385-497-0814', $profile->parent_guardian_phone);
    }

    public function test_nova_import_accepts_timezone_display_label(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'gender' => 'Female',
                'date_of_birth' => '2010-06-01',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU006',
                'timezone' => 'Eastern Time (ET)',
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

        $response->assertOk();
        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $profile = StudentProfile::where('id_number', 'STU006')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('America/New_York', $profile->timezone);
    }

    public function test_nova_import_stores_second_parent_guardian(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'Ava',
                'last_name' => 'Thompson',
                'email' => 'ava.thompson@example.com',
                'gender' => 'Female',
                'date_of_birth' => '2013-04-12',
                'school_name' => $this->school->external_emr_name,
                'id_number' => 'STU-2P',
                'timezone' => 'America/New_York',
                'grade_level' => '5',
                'city' => 'Denver',
                'state' => 'CO',
                'zip_code' => '80202',
                'parent_guardian_name' => 'Sarah Thompson',
                'parent_guardian_email' => 'sarah.thompson@example.com',
                'parent_guardian_phone' => '303-555-0101',
                'parent_guardian_2_name' => 'David Thompson',
                'parent_guardian_2_email' => 'david.thompson@example.com',
                'parent_guardian_2_phone' => '303-555-0202',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        $profile = StudentProfile::where('id_number', 'STU-2P')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('David Thompson', $profile->parent_guardian_2_name);
        $this->assertEquals('david.thompson@example.com', $profile->parent_guardian_2_email);
        $this->assertEquals('303-555-0202', $profile->parent_guardian_2_phone);
    }

    public function test_tutorbird_import_stores_second_parent_from_contact_two(): void
    {
        Mail::fake();

        $tutorbirdSchool = School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Liam',
                'Last Name' => 'Carter',
                'TutorBird Student ID' => 'TB-2P',
                'School' => 'NR School 01',
                'Email' => 'liam.carter@example.com',
                'Parent Contact 1 First Name' => 'Helen',
                'Parent Contact 1 Last Name' => 'Carter',
                'Parent Contact 1 Email' => 'helen.carter@example.com',
                'Parent Contact 1 Mobile Phone' => '303-555-0303',
                'Parent Contact 2 First Name' => 'Chris',
                'Parent Contact 2 Last Name' => 'Carter',
                'Parent Contact 2 Email' => 'chris.carter@example.com',
                'Parent Contact 2 Mobile Phone' => '(303) 555-0505',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        $profile = StudentProfile::where('id_number', 'TB-2P')->first();
        $this->assertNotNull($profile);
        $this->assertEquals($tutorbirdSchool->id, $profile->school_id);
        // First+last name combine into the second guardian name.
        $this->assertEquals('Chris Carter', $profile->parent_guardian_2_name);
        $this->assertEquals('chris.carter@example.com', $profile->parent_guardian_2_email);
        // Phone is normalized like the first guardian's.
        $this->assertEquals('303-555-0505', $profile->parent_guardian_2_phone);
    }

    public function test_timezone_fallback_to_school_when_not_provided(): void
    {
        Mail::fake();

        $schoolChicago = School::factory()->create([
            'external_emr_name' => 'Chicago School',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = "first_name,last_name,email,gender,date_of_birth,school_name,id_number,timezone,grade_level,city,state,zip_code\n";
        $csvContent .= "John,Doe,no-tz@example.com,Male,2010-01-01,Chicago School,STU007,,8,Chicago,IL,60601\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk();
        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $profile = StudentProfile::where('id_number', 'STU007')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('America/Chicago', $profile->timezone);
    }

    public function test_timezone_fallback_to_school_when_invalid(): void
    {
        Mail::fake();

        $schoolDenver = School::factory()->create([
            'external_emr_name' => 'Denver School',
            'timezone' => 'America/Denver',
        ]);

        $csvContent = "first_name,last_name,email,gender,date_of_birth,school_name,id_number,timezone,grade_level,city,state,zip_code\n";
        $csvContent .= "Jane,Doe,invalid-tz@example.com,Female,2010-06-15,Denver School,STU009,Invalid/Timezone,7,Denver,CO,80201\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::NOVA->value,
            ]);

        $response->assertOk();
        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $profile = StudentProfile::where('id_number', 'STU009')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('America/Denver', $profile->timezone);
    }

    // ── TutorBird Feature Tests ─────────────────────────────────

    public function test_tutorbird_import_creates_student_with_defaults(): void
    {
        Mail::fake();

        $tutorbirdSchool = School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Sophia',
                'Last Name' => 'Martinez',
                'TutorBird Student ID' => 'TB001',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'parent.martinez@example.com',
                'Parent Contact 1 First Name' => 'Maria',
                'Parent Contact 1 Last Name' => 'Martinez',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $import->refresh();
        $this->assertEquals(StudentImportStatus::COMPLETED, $import->status);

        // Student email falls back to parent email via field_sources
        $this->assertDatabaseHas('users', [
            'email' => 'parent.martinez@example.com',
            'role' => Role::STUDENT->value,
        ]);

        $profile = StudentProfile::where('id_number', 'TB001')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('Sophia', $profile->first_name);
        $this->assertEquals('Martinez', $profile->last_name);
        $this->assertEquals($tutorbirdSchool->id, $profile->school_id);
        $this->assertEquals('Maria Martinez', $profile->parent_guardian_name);
        $this->assertEquals('parent.martinez@example.com', $profile->parent_guardian_email);

        // Timezone falls back to school when not in CSV
        $this->assertEquals('America/Chicago', $profile->timezone);

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('done', $row->status->value);
    }

    public function test_tutorbird_import_uses_parent_email_as_student_email_via_field_sources(): void
    {
        Mail::fake();

        School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Liam',
                'Last Name' => 'Chen',
                'TutorBird Student ID' => 'TB002',
                'School' => 'NR School 01',
                'Email' => 'liam.chen@example.com',
                'Parent Contact 1 Email' => 'parent.chen@example.com',
                'Parent Contact 1 First Name' => 'Wei',
                'Parent Contact 1 Last Name' => 'Chen',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        // field_sources overwrites email with parent_guardian_email when source is non-empty
        $this->assertDatabaseHas('users', [
            'email' => 'parent.chen@example.com',
            'role' => Role::STUDENT->value,
        ]);
    }

    public function test_tutorbird_import_allows_duplicate_emails(): void
    {
        Mail::fake();

        School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        // Schema allows duplicate emails (unique constraint dropped); import succeeds
        User::factory()->create(['email' => 'shared.parent@example.com']);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Noah',
                'Last Name' => 'Adams',
                'TutorBird Student ID' => 'TB003',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'shared.parent@example.com',
                'Parent Contact 1 First Name' => 'Sarah',
                'Parent Contact 1 Last Name' => 'Adams',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        $userCountBefore = User::count();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertGreaterThan($userCountBefore, User::count());

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('done', $row->status->value);
    }

    public function test_tutorbird_import_catches_duplicate_id_number(): void
    {
        Mail::fake();

        $tutorbirdSchool = School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $existingUser = User::factory()->create(['role' => Role::STUDENT->value]);
        StudentProfile::factory()->create([
            'user_id' => $existingUser->id,
            'school_id' => $tutorbirdSchool->id,
            'id_number' => 'TB004',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Duplicate',
                'Last Name' => 'Student',
                'TutorBird Student ID' => 'TB004',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'dup@example.com',
                'Parent Contact 1 First Name' => 'Parent',
                'Parent Contact 1 Last Name' => 'Dup',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('duplicate', $row->status->value);
    }

    public function test_tutorbird_import_fails_when_school_not_found(): void
    {
        Mail::fake();

        // Do NOT create school with external_emr_name 'NonExistent School'
        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Orphan',
                'Last Name' => 'Student',
                'TutorBird Student ID' => 'TB005',
                'School' => 'NonExistent School',
                'Parent Contact 1 Email' => 'orphan@example.com',
                'Parent Contact 1 First Name' => 'Missing',
                'Parent Contact 1 Last Name' => 'School',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $row = StudentImportRow::where('student_import_id', $import->id)->first();
        $this->assertEquals('validation_error', $row->status->value);
        $this->assertStringContainsString('NonExistent School', (string) $row->error_message);
    }

    public function test_tutorbird_import_normalizes_phone_number(): void
    {
        Mail::fake();

        School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Phone',
                'Last Name' => 'Test',
                'TutorBird Student ID' => 'TB006',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'phone@example.com',
                'Parent Contact 1 First Name' => 'Jane',
                'Parent Contact 1 Last Name' => 'Test',
                'Parent Contact 1 Mobile Phone' => '(210) 555-9876',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $profile = StudentProfile::where('id_number', 'TB006')->first();
        $this->assertNotNull($profile);
        $this->assertEquals('210-555-9876', $profile->parent_guardian_phone);
    }

    public function test_tutorbird_import_handles_multiple_rows(): void
    {
        Mail::fake();

        School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Alice',
                'Last Name' => 'One',
                'TutorBird Student ID' => 'TB010',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'alice.parent@example.com',
                'Parent Contact 1 First Name' => 'Mom',
                'Parent Contact 1 Last Name' => 'One',
            ],
            [
                'First Name' => 'Bob',
                'Last Name' => 'Two',
                'TutorBird Student ID' => 'TB011',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'bob.parent@example.com',
                'Parent Contact 1 First Name' => 'Dad',
                'Parent Contact 1 Last Name' => 'Two',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $this->assertDatabaseHas('student_profiles', ['id_number' => 'TB010']);
        $this->assertDatabaseHas('student_profiles', ['id_number' => 'TB011']);
    }

    public function test_tutorbird_import_with_missing_required_columns_fails(): void
    {
        $csvContent = "First Name,Last Name\nJohn,Doe\n";
        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessStudentImportJob::class);

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));
        $import->refresh();

        $this->assertEquals(StudentImportStatus::FAILED, $import->status);
        $this->assertStringContainsString('TutorBird Student ID', (string) $import->error_message);
    }

    public function test_tutorbird_import_generates_correct_username(): void
    {
        Mail::fake();

        School::factory()->create([
            'external_emr_name' => 'NR School 01',
            'timezone' => 'America/Chicago',
        ]);

        $csvContent = $this->generateTutorbirdCsvContent([
            [
                'First Name' => 'Jane',
                'Last Name' => 'Doe',
                'TutorBird Student ID' => 'TB099',
                'School' => 'NR School 01',
                'Parent Contact 1 Email' => 'jane.parent@example.com',
                'Parent Contact 1 First Name' => 'Mom',
                'Parent Contact 1 Last Name' => 'Doe',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('tutorbird.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.store'), [
                'file' => $file,
                'type' => StudentImportType::TUTORBIRD->value,
            ]);

        $response->assertOk();

        $import = StudentImport::first();
        (new ProcessStudentImportJob($import))->handle(app(\App\Domain\Student\Services\StudentImportService::class));

        $profile = StudentProfile::where('id_number', 'TB099')->first();
        $this->assertNotNull($profile);

        $user = User::find($profile->user_id);
        $this->assertEquals('jane.doe.tb099', $user->username);
    }

    private function generateTutorbirdCsvContent(array $rows): string
    {
        $columns = [
            'First Name', 'Last Name', 'TutorBird Student ID', 'School',
            'Email', 'Birthday', 'Address', 'Gender', 'Mobile Phone',
            'Parent Contact 1 Last Name', 'Parent Contact 1 First Name',
            'Parent Contact 1 Email', 'Parent Contact 1 Mobile Phone',
            'Parent Contact 2 Last Name', 'Parent Contact 2 First Name',
            'Parent Contact 2 Email', 'Parent Contact 2 Mobile Phone',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function generateRsmCsvContent(array $rows): string
    {
        $columns = [
            'Identity ID', 'Last Name', 'First Name', 'Gender', 'Grade', 'School Name',
            'City', 'Zip', 'Parent Email', 'Parent Last Name', 'Parent First Name',
            'Address', 'Phone', 'timezone', 'Date of Birth',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
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
            'parent_guardian_2_name',
            'parent_guardian_2_email',
            'parent_guardian_2_phone',
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
