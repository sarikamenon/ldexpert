<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateStudentCommentDTO;
use Tests\TestCase;

final class CreateStudentCommentDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_expected_types(): void
    {
        $data = [
            'student_id' => '10',
            'author_id' => '5',
            'comment' => '  Test comment  ',
        ];

        $dto = CreateStudentCommentDTO::fromArray($data);

        $this->assertSame(10, $dto->studentId);
        $this->assertSame(5, $dto->authorId);
        $this->assertSame('Test comment', $dto->comment); // Should be trimmed
    }

    public function test_to_array_serializes_values(): void
    {
        $dto = new CreateStudentCommentDTO(
            studentId: 10,
            authorId: 5,
            comment: 'Test comment',
        );

        $array = $dto->toArray();

        $this->assertSame([
            'student_id' => 10,
            'author_id' => 5,
            'comment' => 'Test comment',
        ], $array);
    }

    public function test_from_array_handles_integer_values(): void
    {
        $data = [
            'student_id' => 10,
            'author_id' => 5,
            'comment' => 'Test comment',
        ];

        $dto = CreateStudentCommentDTO::fromArray($data);

        $this->assertSame(10, $dto->studentId);
        $this->assertSame(5, $dto->authorId);
    }
}
