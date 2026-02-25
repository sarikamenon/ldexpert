<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Student\Repositories\StudentCommentRepositoryInterface;
use App\DTOs\CreateStudentCommentDTO;
use App\Models\StudentComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentStudentCommentRepository implements StudentCommentRepositoryInterface
{
    public function create(CreateStudentCommentDTO $dto): StudentComment
    {
        return StudentComment::create($dto->toArray());
    }

    /** @return LengthAwarePaginator<int, StudentComment> */
    public function listByStudent(int $studentId, int $perPage = 15): LengthAwarePaginator
    {
        return StudentComment::query()
            ->where('student_id', $studentId)
            ->with(['author'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function countByStudent(int $studentId): int
    {
        return StudentComment::query()
            ->where('student_id', $studentId)
            ->count();
    }
}
