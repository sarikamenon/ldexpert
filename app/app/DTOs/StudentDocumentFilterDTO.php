<?php

declare(strict_types=1);

namespace App\DTOs;

final class StudentDocumentFilterDTO
{
    public function __construct(
        public readonly ?int $studentId = null,
        public readonly ?string $documentType = null,
        public readonly ?int $uploadedById = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            studentId: isset($data['student_id']) && $data['student_id'] !== '' ? (int) $data['student_id'] : null,
            documentType: isset($data['document_type']) && $data['document_type'] !== '' ? (string) $data['document_type'] : null,
            uploadedById: isset($data['uploaded_by_id']) && $data['uploaded_by_id'] !== '' ? (int) $data['uploaded_by_id'] : null,
            search: isset($data['search']) && $data['search'] !== '' ? trim($data['search']) : null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
            page: isset($data['page']) ? (int) $data['page'] : 1,
        );
    }

    public static function fromRequest(array $data): self
    {
        return self::fromArray($data);
    }
}
