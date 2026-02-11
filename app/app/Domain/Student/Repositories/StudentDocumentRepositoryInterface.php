<?php

declare(strict_types=1);

namespace App\Domain\Student\Repositories;

use App\DTOs\StudentDocumentFilterDTO;
use App\Models\StudentDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StudentDocumentRepositoryInterface
{
    public function create(array $data): StudentDocument;

    public function find(int $id): ?StudentDocument;

    public function list(StudentDocumentFilterDTO $filters): LengthAwarePaginator;

    public function listByStudent(int $studentId): Collection;

    public function listBySessionLog(int $sessionLogId): Collection;

    public function delete(StudentDocument $document): bool;
}
