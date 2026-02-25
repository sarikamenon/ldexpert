<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentCommentRepositoryInterface;
use App\DTOs\CreateStudentCommentDTO;
use App\Models\StudentComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class StudentCommentService
{
    public function __construct(
        private readonly StudentCommentRepositoryInterface $repository,
    ) {}

    public function create(CreateStudentCommentDTO $dto): StudentComment
    {
        return $this->repository->create($dto);
    }

    /** @return LengthAwarePaginator<int, StudentComment> */
    public function listByStudent(int $studentId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->listByStudent($studentId, $perPage);
    }
}
