<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\SessionLog;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SessionLogDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $therapist;

    private User $student;

    private SessionLog $sessionLog;

    protected function setUp(): void
    {
        parent::setUp();

        $disk = config('filesystems.default');
        Storage::fake($disk);

        $this->therapist = User::factory()->create([
            'role' => Role::THERAPIST,
        ]);

        $this->student = User::factory()->create([
            'role' => Role::STUDENT,
        ]);

        $this->sessionLog = SessionLog::factory()->create([
            'therapist_id' => $this->therapist->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_therapist_can_upload_document_to_session_log(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.session-logs.documents.store', $this->sessionLog), [
                'file' => $file,
                'document_type' => DocumentType::ASSESSMENT->value,
                'description' => 'Session assessment',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('student_documents', [
            'documentable_type' => SessionLog::class,
            'documentable_id' => $this->sessionLog->id,
            'uploaded_by_id' => $this->therapist->id,
            'document_type' => DocumentType::ASSESSMENT->value,
        ]);
    }

    public function test_therapist_can_download_their_session_log_document(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        $filePath = 'student-documents/2026/01/test.pdf';
        Storage::disk($disk)->put($filePath, 'test content');

        $document = StudentDocument::factory()->forSessionLog($this->sessionLog)->create([
            'uploaded_by_id' => $this->therapist->id,
            'file_path' => $filePath,
            'file_name' => 'test.pdf',
        ]);

        $response = $this->actingAs($this->therapist)
            ->get(route('therapist.session-logs.documents.download', [
                'sessionLog' => $this->sessionLog,
                'document' => $document,
            ]));

        $response->assertStatus(200);
        $response->assertDownload('test.pdf');
    }

    public function test_therapist_can_delete_their_own_document(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        $filePath = 'student-documents/2026/01/test.pdf';
        Storage::disk($disk)->put($filePath, 'test content');

        $document = StudentDocument::factory()->forSessionLog($this->sessionLog)->create([
            'uploaded_by_id' => $this->therapist->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($this->therapist)
            ->deleteJson(route('therapist.session-logs.documents.destroy', [
                'sessionLog' => $this->sessionLog,
                'document' => $document,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertSoftDeleted('student_documents', [
            'id' => $document->id,
        ]);
    }

    public function test_therapist_cannot_upload_to_other_therapist_session_log(): void
    {
        $otherTherapist = User::factory()->create(['role' => Role::THERAPIST]);
        $otherSessionLog = SessionLog::factory()->create([
            'therapist_id' => $otherTherapist->id,
            'student_id' => $this->student->id,
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.session-logs.documents.store', $otherSessionLog), [
                'file' => $file,
                'document_type' => DocumentType::PROGRESS_REPORT->value,
            ]);

        $response->assertStatus(404);
    }
}
