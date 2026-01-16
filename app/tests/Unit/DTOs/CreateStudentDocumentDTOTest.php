<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateStudentDocumentDTO;
use App\Enums\DocumentType;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class CreateStudentDocumentDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_expected_types(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $data = [
            'documentable_type' => User::class,
            'documentable_id' => '10',
            'uploaded_by_id' => '5',
            'document_type' => DocumentType::PROGRESS_REPORT->value,
            'file' => $file,
            'description' => '  Test description  ',
        ];

        $dto = CreateStudentDocumentDTO::fromArray($data);

        $this->assertSame(User::class, $dto->documentableType);
        $this->assertSame(10, $dto->documentableId);
        $this->assertSame(5, $dto->uploadedById);
        $this->assertSame(DocumentType::PROGRESS_REPORT, $dto->documentType);
        $this->assertSame($file, $dto->file);
        $this->assertSame('Test description', $dto->description); // Should be trimmed
    }

    public function test_to_array_serializes_values(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $dto = new CreateStudentDocumentDTO(
            documentableType: SessionLog::class,
            documentableId: 20,
            uploadedById: 5,
            documentType: DocumentType::IEP,
            file: $file,
            description: 'Test description',
        );

        $array = $dto->toArray();

        $this->assertSame([
            'documentable_type' => SessionLog::class,
            'documentable_id' => 20,
            'uploaded_by_id' => 5,
            'document_type' => DocumentType::IEP->value,
            'description' => 'Test description',
        ], $array);
    }

    public function test_from_array_handles_null_description(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $data = [
            'documentable_type' => User::class,
            'documentable_id' => 10,
            'uploaded_by_id' => 5,
            'document_type' => DocumentType::OTHER->value,
            'file' => $file,
        ];

        $dto = CreateStudentDocumentDTO::fromArray($data);

        $this->assertNull($dto->description);
    }
}
