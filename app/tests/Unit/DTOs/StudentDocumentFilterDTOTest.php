<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\StudentDocumentFilterDTO;
use Tests\TestCase;

final class StudentDocumentFilterDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_all_filters(): void
    {
        $data = [
            'student_id' => '10',
            'document_type' => 'progress_report',
            'uploaded_by_id' => '5',
            'search' => '  test search  ',
            'per_page' => '25',
            'page' => '2',
        ];

        $dto = StudentDocumentFilterDTO::fromArray($data);

        $this->assertSame(10, $dto->studentId);
        $this->assertSame('progress_report', $dto->documentType);
        $this->assertSame(5, $dto->uploadedById);
        $this->assertSame('test search', $dto->search); // Should be trimmed
        $this->assertSame(25, $dto->perPage);
        $this->assertSame(2, $dto->page);
    }

    public function test_from_array_handles_empty_strings_as_null(): void
    {
        $data = [
            'student_id' => '',
            'document_type' => '',
            'uploaded_by_id' => '',
            'search' => '',
        ];

        $dto = StudentDocumentFilterDTO::fromArray($data);

        $this->assertNull($dto->studentId);
        $this->assertNull($dto->documentType);
        $this->assertNull($dto->uploadedById);
        $this->assertNull($dto->search);
    }

    public function test_from_array_uses_defaults(): void
    {
        $data = [];

        $dto = StudentDocumentFilterDTO::fromArray($data);

        $this->assertNull($dto->studentId);
        $this->assertNull($dto->documentType);
        $this->assertNull($dto->uploadedById);
        $this->assertNull($dto->search);
        $this->assertSame(15, $dto->perPage);
        $this->assertSame(1, $dto->page);
    }
}
