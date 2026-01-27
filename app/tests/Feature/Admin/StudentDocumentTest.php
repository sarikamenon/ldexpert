<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StudentDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create([
            'role' => Role::ADMIN,
        ]);

        $this->student = User::factory()->create([
            'role' => Role::STUDENT,
        ]);
    }

    public function test_admin_can_view_student_documents_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.student-documents.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.student-documents.index');
    }

    public function test_admin_can_upload_document_to_student(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.student-documents.store', $this->student), [
                'file' => $file,
                'document_type' => DocumentType::PROGRESS_REPORT->value,
                'description' => 'Test document',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('student_documents', [
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->admin->id,
            'document_type' => DocumentType::PROGRESS_REPORT->value,
            'description' => 'Test document',
        ]);
    }

    public function test_admin_is_redirected_after_uploading_document_via_form(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.student-documents.store', $this->student), [
                'file' => $file,
                'document_type' => DocumentType::PROGRESS_REPORT->value,
                'description' => 'Test document',
            ]);

        $response->assertRedirect(route('admin.students.show', [
            'student' => $this->student,
            'tab' => 'documents',
        ]));
        $response->assertSessionHas('success', 'Document uploaded successfully.');

        $this->assertDatabaseHas('student_documents', [
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->admin->id,
            'document_type' => DocumentType::PROGRESS_REPORT->value,
            'description' => 'Test document',
        ]);
    }

    public function test_admin_can_download_document(): void
    {
        Storage::fake('local');
        $filePath = 'student-documents/2026/01/test.pdf';
        Storage::disk('local')->put($filePath, 'test content');

        $document = StudentDocument::factory()->create([
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->admin->id,
            'file_path' => $filePath,
            'file_name' => 'test.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.student-documents.download', $document));

        $response->assertStatus(200);
        $response->assertDownload('test.pdf');
    }

    public function test_admin_can_delete_document(): void
    {
        Storage::fake('local');
        $filePath = 'student-documents/2026/01/test.pdf';
        Storage::disk('local')->put($filePath, 'test content');

        $document = StudentDocument::factory()->create([
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->admin->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson(route('admin.student-documents.destroy', $document));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertSoftDeleted('student_documents', [
            'id' => $document->id,
        ]);
    }

    public function test_admin_can_filter_documents_by_student(): void
    {
        $student2 = User::factory()->create(['role' => Role::STUDENT]);

        StudentDocument::factory()->forStudent($this->student)->create();
        StudentDocument::factory()->forStudent($student2)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.student-documents.index', ['student_id' => $this->student->id]));

        $response->assertStatus(200);
        $documents = $response->viewData('documents');
        $this->assertCount(1, $documents->items());
    }
}
