<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ServiceStatus;
use App\Enums\SSAImportRowStatus;
use App\Enums\SSAImportStatus;
use App\Enums\SSAImportType;
use App\Enums\UserStatus;
use App\Jobs\ProcessSSAImportJob;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAImport;
use App\Models\SSAImportRow;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SSAImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Service $service;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->school = School::factory()->create([
            'external_emr_name' => 'Test School EMR',
        ]);
        $this->student = User::factory()->student()->create([
            'email' => 'student@example.com',
            'status' => UserStatus::ACTIVE,
        ]);
        StudentProfile::factory()->create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'id_number' => 'STU001',
        ]);
        $this->service = Service::factory()->create([
            'name' => 'Speech Therapy',
            'status' => ServiceStatus::ACTIVE,
            'is_frequency_service' => false,
            'is_direct_service' => true,
        ]);

        $disk = config('filesystems.default');
        Storage::fake($disk);
        Bus::fake();
    }

    public function test_admin_can_view_import_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.ssas.import'));

        $response->assertOk()
            ->assertViewIs('admin.ssas.import')
            ->assertViewHas('requiredColumns')
            ->assertViewHas('optionalColumns')
            ->assertViewHas('importTypes');
    }

    public function test_non_admin_cannot_view_import_form(): void
    {
        $response = $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.ssas.import'));

        $response->assertForbidden();
    }

    public function test_admin_can_download_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.ssas.import.template'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'ssa-import-template-',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_admin_can_import_valid_csv(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'student_email' => $this->student->email,
                'primary_service_name' => $this->service->name,
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'minutes_per_session' => '60',
                'tho_minutes' => '1200',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('ssas.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::NOVA->value,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Bus::assertDispatched(ProcessSSAImportJob::class);

        $import = SSAImport::first();
        $this->assertNotNull($import);
        $this->assertEquals(SSAImportStatus::PENDING, $import->status);
        $this->assertEquals(1, $import->total_rows);

        // Process the import
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $import->refresh();
        $this->assertEquals(SSAImportStatus::COMPLETED, $import->status);

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        $this->assertNotNull($row);
        if ($row->status->value !== 'done') {
            $this->fail('Row status is '.$row->status->value.' with error: '.($row->error_message ?? 'No error message'));
        }
        $this->assertEquals('done', $row->status->value);
        $this->assertNotNull($row->ssa_id);

        $ssa = ServiceSupportAgreement::find($row->ssa_id);
        $this->assertNotNull($ssa);
        $this->assertEquals($this->student->id, $ssa->student_id);
        $this->assertEquals($this->service->id, $ssa->primary_service_id);
    }

    public function test_import_fails_with_missing_required_columns(): void
    {
        Mail::fake();

        $csvContent = "invalid_column\nvalue";
        $file = UploadedFile::fake()->createWithContent('ssas.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::NOVA->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessSSAImportJob::class);

        $import = SSAImport::first();
        $this->assertNotNull($import);
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $import->refresh();
        $this->assertEquals(SSAImportStatus::FAILED, $import->status);
        $this->assertStringContainsString('Missing required columns', (string) $import->error_message);
    }

    public function test_import_handles_validation_errors(): void
    {
        Mail::fake();

        $csvContent = $this->generateCsvContent([
            [
                'student_email' => 'nonexistent@example.com',
                'primary_service_name' => $this->service->name,
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'minutes_per_session' => '60',
                'tho_minutes' => '1200',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('ssas.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::NOVA->value,
            ]);

        Bus::assertDispatched(ProcessSSAImportJob::class);

        $import = SSAImport::first();
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $import->refresh();
        $this->assertEquals(SSAImportStatus::COMPLETED, $import->status);

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        $this->assertEquals('validation_error', $row->status->value);
        $this->assertNotNull($row->error_message);
    }

    public function test_import_detects_duplicate_ssas(): void
    {
        Mail::fake();

        // Create an existing SSA
        ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $this->service->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => \App\Enums\SSAStatus::ACTIVE,
        ]);

        $csvContent = $this->generateCsvContent([
            [
                'student_email' => $this->student->email,
                'primary_service_name' => $this->service->name,
                'start_date' => '2025-06-01',
                'end_date' => '2025-12-31',
                'minutes_per_session' => '60',
                'tho_minutes' => '1200',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('ssas.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::NOVA->value,
            ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessSSAImportJob::class);

        $import = SSAImport::first();
        $this->assertNotNull($import);
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $import->refresh();
        $this->assertEquals(SSAImportStatus::COMPLETED, $import->status);

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        $this->assertNotNull($row);
        $this->assertEquals('duplicate', $row->status->value);
        $this->assertNotNull($row->error_message);
    }

    public function test_admin_can_view_import_history(): void
    {
        SSAImport::create([
            'user_id' => $this->admin->id,
            'type' => SSAImportType::NOVA,
            'file_path' => 'test/path.csv',
            'file_name' => 'test.csv',
            'total_rows' => 1,
            'processed_rows' => 0,
            'status' => SSAImportStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ssas.imports.index'));

        $response->assertOk()
            ->assertViewIs('admin.ssas.import-history');
    }

    public function test_admin_can_view_import_status(): void
    {
        $import = SSAImport::create([
            'user_id' => $this->admin->id,
            'type' => SSAImportType::NOVA,
            'file_path' => 'test/path.csv',
            'file_name' => 'test.csv',
            'total_rows' => 1,
            'processed_rows' => 1,
            'status' => SSAImportStatus::COMPLETED,
        ]);

        SSAImportRow::create([
            'ssa_import_id' => $import->id,
            'row_number' => 1,
            'status' => SSAImportRowStatus::DONE,
            'raw_data' => [],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ssas.imports.show', $import));

        $response->assertOk()
            ->assertViewIs('admin.ssas.import-status');
    }

    public function test_admin_can_import_rsm_csv(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Status (3=WD)' => '1',
                'Identity ID' => 'STU001',
                'Last Name' => 'Doe',
                'First Name' => 'Jane',
                'School Name' => 'Test School EMR',
                'Begin Date' => '01/01/2025',
                'End Date' => '12/31/2025',
                'Service' => $this->service->name,
                'Hours' => '1.00',
                'How Often' => '1',
                'Per' => 'Week',
                'Total Hrs Owed' => '24.00',
                'Therapist Email' => '',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm-referrals.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::RSM->value,
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        Bus::assertDispatched(ProcessSSAImportJob::class);

        $import = SSAImport::first();
        $this->assertNotNull($import);
        $this->assertEquals(SSAImportType::RSM, $import->type);

        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $import->refresh();
        $this->assertEquals(SSAImportStatus::COMPLETED, $import->status);

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        $this->assertNotNull($row);
        if ($row->status->value !== 'done') {
            $this->fail('Row status is '.$row->status->value.' with error: '.($row->error_message ?? 'No error message'));
        }
        $this->assertNotNull($row->ssa_id);

        // Verify the SSA was created with correct conversions
        $ssa = ServiceSupportAgreement::find($row->ssa_id);
        $this->assertNotNull($ssa);
        $this->assertEquals($this->student->id, $ssa->student_id);
        $this->assertEquals($this->service->id, $ssa->primary_service_id);
        $this->assertEquals(60, $ssa->minutes_per_session); // 1.00 hours × 60
        $this->assertEquals(1440, $ssa->tho_minutes); // 24.00 hours × 60
        $this->assertEquals('weekly', $ssa->frequency->value);
        $this->assertEquals(1, $ssa->sessions_per_frequency);
    }

    public function test_rsm_import_converts_hours_to_minutes(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Identity ID' => 'STU001',
                'School Name' => 'Test School EMR',
                'Begin Date' => '01/01/2025',
                'End Date' => '06/30/2025',
                'Service' => $this->service->name,
                'Hours' => '0.50',
                'How Often' => '2',
                'Per' => 'Month',
                'Total Hrs Owed' => '7.50',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::RSM->value,
            ]);

        $import = SSAImport::first();
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        if ($row->status->value !== 'done') {
            $this->fail('Row status is '.$row->status->value.' with error: '.($row->error_message ?? 'No error message'));
        }

        $ssa = ServiceSupportAgreement::find($row->ssa_id);
        $this->assertEquals(30, $ssa->minutes_per_session); // 0.50 × 60
        $this->assertEquals(450, $ssa->tho_minutes); // 7.50 × 60
        $this->assertEquals('monthly', $ssa->frequency->value);
        $this->assertEquals(2, $ssa->sessions_per_frequency);
    }

    public function test_rsm_import_student_not_found(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Identity ID' => 'NONEXISTENT',
                'School Name' => 'Test School EMR',
                'Begin Date' => '01/01/2025',
                'End Date' => '12/31/2025',
                'Service' => $this->service->name,
                'Hours' => '1.00',
                'Total Hrs Owed' => '24.00',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::RSM->value,
            ]);

        $import = SSAImport::first();
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        $this->assertEquals('validation_error', $row->status->value);
        $this->assertStringContainsString('Student not found', (string) $row->error_message);
    }

    public function test_rsm_import_service_not_found(): void
    {
        Mail::fake();

        $csvContent = $this->generateRsmCsvContent([
            [
                'Identity ID' => 'STU001',
                'School Name' => 'Test School EMR',
                'Begin Date' => '01/01/2025',
                'End Date' => '12/31/2025',
                'Service' => 'Nonexistent Service',
                'Hours' => '1.00',
                'Total Hrs Owed' => '24.00',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('rsm.csv', $csvContent);

        $this->actingAs($this->admin)
            ->postJson(route('admin.ssas.import.store'), [
                'file' => $file,
                'type' => SSAImportType::RSM->value,
            ]);

        $import = SSAImport::first();
        (new ProcessSSAImportJob($import))->handle(app(\App\Domain\SSA\Services\SSAImportService::class));

        $row = SSAImportRow::where('ssa_import_id', $import->id)->first();
        $this->assertEquals('validation_error', $row->status->value);
        $this->assertStringContainsString('not found', (string) $row->error_message);
    }

    public function test_admin_can_download_rsm_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.ssas.import.template', ['type' => 'RSM']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString(
            'ssa-import-template-rsm-',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_admin_view_import_form_has_templates(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.ssas.import'));

        $response->assertOk()
            ->assertViewHas('templates');
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function generateCsvContent(array $rows): string
    {
        $requiredColumns = [
            'student_email',
            'primary_service_name',
            'start_date',
            'end_date',
            'minutes_per_session',
            'tho_minutes',
        ];

        $optionalColumns = [
            'student_id_number',
            'school_name',
            'additional_service_names',
            'frequency',
            'sessions_per_frequency',
            'assigned_therapist_email',
            'calculated_minutes',
            'adjusted_minutes',
            'adjustment_notes',
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

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function generateRsmCsvContent(array $rows): string
    {
        $columns = [
            'Status (3=WD)',
            'Identity ID',
            'Last Name',
            'First Name',
            'Gender',
            'Grade',
            'School Name',
            'Address',
            'City',
            'Zip',
            'Phone',
            'Parent Email',
            'Parent Last Name',
            'Parent First Name',
            'Relationship',
            'SE Teacher',
            'SE Teacher Email',
            'Primary Teacher',
            'Primary Teacher Email',
            'Payment Entity',
            'End Date',
            'Begin Date',
            'IEP Begin Date',
            'Service',
            'Hours',
            'How Often',
            'Per',
            'Notes',
            'Teacher Notes',
            'Group Therapy Requested?',
            'Date Provider Assigned',
            'Therapist First',
            'Therapist Last',
            'Therapist Email',
            'Type',
            'Total Hrs Owed',
            'Total Hrs Remaining',
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
}
