<?php

declare(strict_types=1);

namespace App\Domain\Student\Repositories;

use App\DTOs\CreateStudentCommentDTO;
use App\Models\StudentComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentCommentRepositoryInterface
{
    public function create(CreateStudentCommentDTO $dto): StudentComment;

    /** @return LengthAwarePaginator<int, StudentComment> */
    public function listByStudent(int $studentId, int $perPage = 15): LengthAwarePaginator;

    public function countByStudent(int $studentId): int;
}
