<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateStudentCommentDTO
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $authorId,
        public readonly string $comment,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            studentId: (int) $data['student_id'],
            authorId: (int) $data['author_id'],
            comment: trim($data['comment']),
        );
    }

    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'author_id' => $this->authorId,
            'comment' => $this->comment,
        ];
    }
}
