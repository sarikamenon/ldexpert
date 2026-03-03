<?php

declare(strict_types=1);

namespace App\Domain\Student\Repositories;

use App\DTOs\StudentDocumentFilterDTO;
use App\Models\StudentDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StudentDocumentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): StudentDocument;

    public function find(int $id): ?StudentDocument;

    /** @return LengthAwarePaginator<int, StudentDocument> */
    public function list(StudentDocumentFilterDTO $filters): LengthAwarePaginator;

    /** @return Collection<int, StudentDocument> */
    public function listByStudent(int $studentId): Collection;

    /** @return Collection<int, StudentDocument> */
    public function listBySessionLog(int $sessionLogId): Collection;

    public function delete(StudentDocument $document): bool;
}
