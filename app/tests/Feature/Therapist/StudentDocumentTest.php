<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\DocumentType;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StudentDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $therapist;

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $disk = config('filesystems.default');
        Storage::fake($disk);

        $this->therapist = User::factory()->therapist()->create();
        $this->student = User::factory()->student()->create();
        $this->otherStudent = User::factory()->student()->create();

        ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'assigned_therapist_id' => $this->therapist->id,
        ]);
    }

    public function test_therapist_can_upload_common_document_for_assigned_student(): void
    {
        $file = UploadedFile::fake()->create('common-doc.pdf', 100);

        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.students.documents.store', $this->student), [
                'file' => $file,
                'document_type' => DocumentType::COMMON->value,
                'description' => 'Common reference document',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Document uploaded successfully.',
            ])
            ->assertJsonPath('document.document_type', 'Common Document');

        $this->assertDatabaseHas('student_documents', [
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->therapist->id,
            'document_type' => DocumentType::COMMON->value,
            'description' => 'Common reference document',
        ]);
    }

    public function test_therapist_cannot_upload_document_for_unassigned_student(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.students.documents.store', $this->otherStudent), [
                'file' => $file,
                'document_type' => DocumentType::COMMON->value,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('student_documents', [
            'documentable_id' => $this->otherStudent->id,
            'uploaded_by_id' => $this->therapist->id,
        ]);
    }

    public function test_therapist_can_download_student_document_for_assigned_student(): void
    {
        $disk = config('filesystems.default');
        $filePath = 'student-documents/2026/01/test.pdf';
        Storage::disk($disk)->put($filePath, 'test content');

        $document = StudentDocument::factory()->create([
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->therapist->id,
            'file_path' => $filePath,
            'file_name' => 'test.pdf',
        ]);

        $response = $this->actingAs($this->therapist)
            ->get(route('therapist.student-documents.download', $document));

        $response->assertOk();
        $response->assertDownload('test.pdf');
    }

    public function test_therapist_can_delete_own_uploaded_student_document(): void
    {
        $disk = config('filesystems.default');
        $filePath = 'student-documents/2026/01/test.pdf';
        Storage::disk($disk)->put($filePath, 'test content');

        $document = StudentDocument::factory()->create([
            'documentable_type' => User::class,
            'documentable_id' => $this->student->id,
            'uploaded_by_id' => $this->therapist->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($this->therapist)
            ->deleteJson(route('therapist.student-documents.destroy', $document));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Document deleted successfully.',
            ]);

        $this->assertSoftDeleted('student_documents', [
            'id' => $document->id,
        ]);
    }
}
