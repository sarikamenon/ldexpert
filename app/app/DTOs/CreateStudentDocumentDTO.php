<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\DocumentType;
use Illuminate\Http\UploadedFile;

final class CreateStudentDocumentDTO
{
    public function __construct(
        public readonly string $documentableType,
        public readonly int $documentableId,
        public readonly int $uploadedById,
        public readonly DocumentType $documentType,
        public readonly UploadedFile $file,
        public readonly ?string $description = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            documentableType: $data['documentable_type'],
            documentableId: (int) $data['documentable_id'],
            uploadedById: (int) $data['uploaded_by_id'],
            documentType: DocumentType::from($data['document_type']),
            file: $data['file'],
            description: isset($data['description']) ? trim($data['description']) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'documentable_type' => $this->documentableType,
            'documentable_id' => $this->documentableId,
            'uploaded_by_id' => $this->uploadedById,
            'document_type' => $this->documentType->value,
            'description' => $this->description,
        ];
    }
}
