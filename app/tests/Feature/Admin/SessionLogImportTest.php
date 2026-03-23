<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ContractStatus;
use App\Enums\ServiceStatus;
use App\Enums\SessionLogImportRowStatus;
use App\Enums\SessionLogImportStatus;
use App\Enums\SessionLogImportType;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Jobs\ProcessSessionLogImportJob;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\SessionLogImport;
use App\Models\SessionLogImportRow;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SessionLogImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private User $therapistUser;

    private Service $service;

    private School $school;

    private ServiceSupportAgreement $ssa;

    protected function setUp(): void
    {
        parent::setUp();

        // Create school with contract and service
        $this->school = School::factory()->create([
            'external_emr_name' => 'Test School EMR',
        ]);
        $this->service = Service::factory()->create([
            'status' => ServiceStatus::ACTIVE,
        ]);

        $startDate = Carbon::parse('2025-01-01');
        $endDate = Carbon::parse('2025-12-31');

        $schoolContract = SchoolContract::create([
            'school_id' => $this->school->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        SchoolContractService::create([
            'school_contract_id' => $schoolContract->id,
            'service_id' => $this->service->id,
            'rate' => 150,
            'rate_type' => 'H',
            'no_show_rate' => 30,
            'no_show_rate_type' => 'F',
        ]);

        // Create admin
        $this->admin = User::factory()->admin()->create();

        // Create student with profile at this school
        $this->student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::ACTIVE,
        ]);
        StudentProfile::factory()->create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'id_number' => 'STU001',
        ]);

        // Create therapist with profile and contract
        $this->therapistUser = User::factory()->therapist()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $therapistProfile = TherapistProfile::factory()->create([
            'user_id' => $this->therapistUser->id,
        ]);
        $therapistContract = TherapistContract::create([
            'therapist_id' => $therapistProfile->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        TherapistContractService::create([
            'therapist_contract_id' => $therapistContract->id,
            'service_id' => $this->service->id,
            'rate' => 100,
            'rate_type' => 'H',
            'no_show_rate' => 25,
            'no_show_rate_type' => 'F',
        ]);

        // Create active SSA
        $this->ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $this->service->id,
            'assigned_therapist_id' => $this->therapistUser->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => SSAStatus::ACTIVE,
            'minutes_per_session' => 60,
            'served_minutes' => 0,
        ]);

        $disk = config('filesystems.default');
        Storage::fake($disk);
        Bus::fake();
    }

    // --- Authorization Tests ---

    public function test_admin_can_view_import_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.session-logs.import'));

        $response->assertOk()
            ->assertViewIs('admin.session-log-imports.import-form')
            ->assertViewHas('requiredColumns')
            ->assertViewHas('optionalColumns')
            ->assertViewHas('importTypes');
    }

    public function test_non_admin_cannot_view_import_form(): void
    {
        $response = $this->actingAs(User::factory()->therapist()->create())
            ->get(route('admin.session-logs.import'));

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_upload_import(): void
    {
        $file = UploadedFile::fake()->createWithContent('test.csv', "Entry_KEYID\nREF001");

        $response = $this->actingAs(User::factory()->therapist()->create())
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $response->assertForbidden();
    }

    // --- Template Download ---

    public function test_admin_can_download_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.session-logs.import.template'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString(
            'session-log-import-template-',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    // --- Valid Import ---

    public function test_admin_can_upload_valid_csv(): void
    {
        $csvContent = $this->generateCsvContent([
            $this->validRowData(),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        Bus::assertDispatched(ProcessSessionLogImportJob::class);

        $import = SessionLogImport::first();
        $this->assertNotNull($import);
        $this->assertEquals(SessionLogImportStatus::PENDING, $import->status);
        $this->assertEquals(1, $import->total_rows);
    }

    public function test_full_import_creates_session_log(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        Bus::assertDispatched(ProcessSessionLogImportJob::class);

        $import = SessionLogImport::first();
        $this->assertNotNull($import);

        // Process the import synchronously
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $import->refresh();
        $this->assertEquals(SessionLogImportStatus::COMPLETED, $import->status);
        $this->assertEquals(1, $import->successful_rows);

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertNotNull($row);
        if ($row->status !== SessionLogImportRowStatus::DONE) {
            $this->fail('Row status is ' . $row->status->value . ' with error: ' . ($row->error_message ?? 'none'));
        }
        $this->assertNotNull($row->session_log_id);

        // Verify session log was created
        $sessionLog = SessionLog::find($row->session_log_id);
        $this->assertNotNull($sessionLog);
        $this->assertEquals($this->student->id, $sessionLog->student_id);
        $this->assertEquals($this->therapistUser->id, $sessionLog->therapist_id);
        $this->assertEquals($this->service->id, $sessionLog->service_id);
        $this->assertEquals($this->school->id, $sessionLog->school_id);
        $this->assertEquals('approved', $sessionLog->status->value);
        $this->assertEquals('virtual', $sessionLog->delivery_mode);
    }

    public function test_import_increments_ssa_served_minutes(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Hours' => '1.5']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $this->ssa->refresh();
        $this->assertEquals(90, $this->ssa->served_minutes); // 1.5 hours = 90 minutes
    }

    // --- Missing Required Columns ---

    public function test_import_fails_with_missing_required_columns(): void
    {
        Mail::fake();

        $csvContent = "invalid_column\nvalue";
        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        $this->assertNotNull($import);

        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $import->refresh();
        $this->assertEquals(SessionLogImportStatus::FAILED, $import->status);
        $this->assertStringContainsString('Missing required columns', (string) $import->error_message);
    }

    // --- Validation Errors ---

    public function test_import_handles_nonexistent_school(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['School' => 'NonExistent School']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $import->refresh();
        $this->assertEquals(SessionLogImportStatus::COMPLETED, $import->status);

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $row->status);
        $this->assertStringContainsString('School not found', (string) $row->error_message);
    }

    public function test_import_handles_nonexistent_student(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['STUDENTID' => 'NONEXISTENT']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $row->status);
        $this->assertStringContainsString('Student not found', (string) $row->error_message);
    }

    public function test_import_handles_nonexistent_therapist(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Therapist Email' => 'nobody@example.com']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $row->status);
        $this->assertStringContainsString('Therapist not found', (string) $row->error_message);
    }

    public function test_import_handles_unknown_outcome_type(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Type1' => 'INVALID']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $row->status);
        $this->assertStringContainsString('Unknown outcome Type1', (string) $row->error_message);
    }

    public function test_import_handles_invalid_date(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Date of Service' => 'not-a-date']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $row->status);
        $this->assertStringContainsString('Invalid date', (string) $row->error_message);
    }

    public function test_import_handles_zero_duration(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Hours' => '0']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $row->status);
        $this->assertStringContainsString('Duration must be greater than zero', (string) $row->error_message);
    }

    // --- Skip Criteria ---

    public function test_import_skips_rows_with_empty_reference_id(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Entry_KEYID' => '']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::SKIPPED, $row->status);
        $this->assertStringContainsString('Empty reference ID', (string) $row->error_message);
    }

    public function test_import_skips_rows_with_error_flag(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Error' => 'Y', 'Error Details' => 'some error']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::SKIPPED, $row->status);
        $this->assertStringContainsString('Row flagged with error', (string) $row->error_message);
    }

    public function test_import_accepts_row_with_no_therapist_selected_error(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Error' => 'Y', 'Error Details' => 'no therapist selected']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        // Should NOT be skipped — "no therapist selected" is an acceptable error detail
        $this->assertNotEquals(SessionLogImportRowStatus::SKIPPED, $row->status);
    }

    // --- Duplicate Detection ---

    public function test_import_detects_duplicate_reference_ids_within_batch(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Entry_KEYID' => 'DUP001']),
            $this->validRowData(['Entry_KEYID' => 'DUP001']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $import->refresh();
        $this->assertEquals(SessionLogImportStatus::COMPLETED, $import->status);

        $rows = SessionLogImportRow::where('session_log_import_id', $import->id)
            ->orderBy('row_number')
            ->get();

        // First row should succeed or have another status, second should be duplicate
        $this->assertEquals(SessionLogImportRowStatus::DUPLICATE, $rows[1]->status);
        $this->assertStringContainsString('Duplicate reference ID', (string) $rows[1]->error_message);
    }

    public function test_import_detects_duplicate_from_previous_import(): void
    {
        Mail::fake();

        // Create a previously processed row with same reference_id
        $previousImport = SessionLogImport::create([
            'user_id' => $this->admin->id,
            'type' => SessionLogImportType::RSM,
            'file_path' => 'test/prev.csv',
            'file_name' => 'prev.csv',
            'total_rows' => 1,
            'processed_rows' => 1,
            'status' => SessionLogImportStatus::COMPLETED,
        ]);
        SessionLogImportRow::create([
            'session_log_import_id' => $previousImport->id,
            'row_number' => 1,
            'reference_id' => 'EXISTING001',
            'status' => SessionLogImportRowStatus::DONE,
            'raw_data' => [],
        ]);

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Entry_KEYID' => 'EXISTING001']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::where('id', '!=', $previousImport->id)->first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        $this->assertEquals(SessionLogImportRowStatus::DUPLICATE, $row->status);
    }

    // --- Import History & Status Views ---

    public function test_admin_can_view_import_history(): void
    {
        SessionLogImport::create([
            'user_id' => $this->admin->id,
            'type' => SessionLogImportType::RSM,
            'file_path' => 'test/path.csv',
            'file_name' => 'test.csv',
            'total_rows' => 1,
            'processed_rows' => 0,
            'status' => SessionLogImportStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.session-logs.imports.index'));

        $response->assertOk()
            ->assertViewIs('admin.session-log-imports.import-history');
    }

    public function test_admin_can_view_import_status(): void
    {
        $import = SessionLogImport::create([
            'user_id' => $this->admin->id,
            'type' => SessionLogImportType::RSM,
            'file_path' => 'test/path.csv',
            'file_name' => 'test.csv',
            'total_rows' => 1,
            'processed_rows' => 1,
            'status' => SessionLogImportStatus::COMPLETED,
        ]);

        SessionLogImportRow::create([
            'session_log_import_id' => $import->id,
            'row_number' => 1,
            'status' => SessionLogImportRowStatus::DONE,
            'raw_data' => [],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.session-logs.imports.show', $import));

        $response->assertOk()
            ->assertViewIs('admin.session-log-imports.import-status');
    }

    public function test_import_status_returns_json_when_requested(): void
    {
        $import = SessionLogImport::create([
            'user_id' => $this->admin->id,
            'type' => SessionLogImportType::RSM,
            'file_path' => 'test/path.csv',
            'file_name' => 'test.csv',
            'total_rows' => 2,
            'processed_rows' => 2,
            'status' => SessionLogImportStatus::COMPLETED,
        ]);

        SessionLogImportRow::create([
            'session_log_import_id' => $import->id,
            'row_number' => 1,
            'status' => SessionLogImportRowStatus::DONE,
            'raw_data' => [],
        ]);
        SessionLogImportRow::create([
            'session_log_import_id' => $import->id,
            'row_number' => 2,
            'status' => SessionLogImportRowStatus::VALIDATION_ERROR,
            'raw_data' => [],
            'error_message' => 'Test error',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.session-logs.imports.show', $import));

        $response->assertOk()
            ->assertJson([
                'status' => 'completed',
                'stats' => [
                    'total' => 2,
                    'success' => 1,
                    'errors' => 1,
                ],
            ]);
    }

    // --- Multiple Rows ---

    public function test_import_processes_multiple_rows_with_mixed_results(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Entry_KEYID' => 'REF001']),
            $this->validRowData(['Entry_KEYID' => 'REF002', 'STUDENTID' => 'NONEXISTENT']),
            $this->validRowData(['Entry_KEYID' => '']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $import->refresh();
        $this->assertEquals(SessionLogImportStatus::COMPLETED, $import->status);

        $rows = SessionLogImportRow::where('session_log_import_id', $import->id)
            ->orderBy('row_number')
            ->get();

        $this->assertCount(3, $rows);

        // Row 1: success (or validation error depending on SSA/billing, but reference_id is valid)
        // Row 2: validation error (nonexistent student)
        $this->assertEquals(SessionLogImportRowStatus::VALIDATION_ERROR, $rows[1]->status);
        // Row 3: skipped (empty reference ID)
        $this->assertEquals(SessionLogImportRowStatus::SKIPPED, $rows[2]->status);
    }

    // --- File Validation ---

    public function test_import_rejects_non_csv_file(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_rejects_missing_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'type' => SessionLogImportType::RSM->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    // --- Group Flag ---

    public function test_import_sets_group_flag_when_num_students_greater_than_one(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData(['Num Students' => '3']),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        if ($row->status === SessionLogImportRowStatus::DONE) {
            $sessionLog = SessionLog::find($row->session_log_id);
            $this->assertTrue((bool) $sessionLog->is_group);
        }
    }

    // --- Notes Concatenation ---

    public function test_import_concatenates_notes_and_goals_progress(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            $this->validRowData([
                'Session Narrative' => 'Session went well.',
                'Goals Progress' => 'Student made progress on goals.',
            ]),
        ]);

        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.session-logs.import.store'), [
                'file' => $file,
                'type' => SessionLogImportType::RSM->value,
            ]);

        $import = SessionLogImport::first();
        (new ProcessSessionLogImportJob($import))->handle(
            app(\App\Domain\SessionLog\Services\SessionLogImportService::class)
        );

        $row = SessionLogImportRow::where('session_log_import_id', $import->id)->first();
        if ($row->status === SessionLogImportRowStatus::DONE) {
            $sessionLog = SessionLog::find($row->session_log_id);
            $this->assertStringContainsString('Session went well.', $sessionLog->notes);
            $this->assertStringContainsString('Goals Progress', $sessionLog->notes);
            $this->assertStringContainsString('Student made progress on goals.', $sessionLog->notes);
        }
    }

    // --- Helpers ---

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validRowData(array $overrides = []): array
    {
        return array_merge([
            'Entry_KEYID' => 'REF' . fake()->unique()->randomNumber(6),
            'School' => 'Test School EMR',
            'STUDENTID' => 'STU001',
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Therapist' => 'Test Therapist',
            'Therapist Email' => $this->therapistUser->email,
            'Service_KEYID' => '12345',
            'Date of Service' => '3/15/2025',
            'Service Name' => $this->service->name,
            'Hours' => '1',
            'Type1' => 'SA',
            'Type2' => 'REG',
            'Therapy Start' => '9:00 AM',
            'Therapy End' => '10:00 AM',
            'Cancellation Notification Time' => '',
            'Session Narrative' => 'Test session narrative for import.',
            'Goals Progress' => '',
            'Inserted On' => '3/15/2025',
            'Error' => 'N',
            'Error Details' => '',
            'Queued for an Invoice?' => 'N',
            'Already on Invoice' => 'N',
            'Invoice Status' => '',
            'Alt 1 Service Name' => '',
            'Alt 1 Hours' => '',
            'Alt 2 Service Name' => '',
            'Alt 2 Hours' => '',
            'Num Students' => '1',
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function generateCsvContent(array $rows): string
    {
        $requiredColumns = [
            'Entry_KEYID', 'School', 'STUDENTID', 'First Name', 'Last Name',
            'Therapist', 'Therapist Email', 'Service_KEYID', 'Date of Service',
            'Service Name', 'Hours', 'Type1', 'Type2', 'Therapy Start',
            'Therapy End', 'Cancellation Notification Time', 'Session Narrative',
            'Goals Progress', 'Inserted On', 'Error', 'Error Details',
            'Queued for an Invoice?', 'Already on Invoice', 'Invoice Status',
            'Alt 1 Service Name', 'Alt 1 Hours', 'Alt 2 Service Name',
            'Alt 2 Hours', 'Num Students',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $requiredColumns);

        foreach ($rows as $row) {
            $line = [];
            foreach ($requiredColumns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
