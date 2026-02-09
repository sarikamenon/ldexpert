<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\DTOs\CreateStudentCommentDTO;
use App\Infrastructure\Repositories\EloquentStudentCommentRepository;
use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentStudentCommentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentStudentCommentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentStudentCommentRepository;
    }

    public function test_create_persists_comment(): void
    {
        $student = User::factory()->student()->create();
        $author = User::factory()->admin()->create();

        $dto = new CreateStudentCommentDTO(
            studentId: $student->id,
            authorId: $author->id,
            comment: 'Test comment',
        );

        $comment = $this->repository->create($dto);

        $this->assertInstanceOf(StudentComment::class, $comment);
        $this->assertDatabaseHas('student_comments', [
            'student_id' => $student->id,
            'author_id' => $author->id,
            'comment' => 'Test comment',
        ]);
    }

    public function test_list_by_student_returns_paginated_comments(): void
    {
        $student = User::factory()->student()->create();
        $author1 = User::factory()->admin()->create();
        $author2 = User::factory()->therapist()->create();

        $firstComment = StudentComment::factory()->create([
            'student_id' => $student->id,
            'author_id' => $author1->id,
            'comment' => 'First comment',
            'created_at' => now()->subMinute(),
        ]);

        $secondComment = StudentComment::factory()->create([
            'student_id' => $student->id,
            'author_id' => $author2->id,
            'comment' => 'Second comment',
            'created_at' => now(),
        ]);

        // Create comment for different student (should not appear)
        $otherStudent = User::factory()->student()->create();
        StudentComment::factory()->create([
            'student_id' => $otherStudent->id,
            'author_id' => $author1->id,
            'comment' => 'Other student comment',
        ]);

        $result = $this->repository->listByStudent($student->id, 15);

        $this->assertCount(2, $result->items());
        $this->assertSame('Second comment', $result->items()[0]->comment); // Newest first
        $this->assertSame('First comment', $result->items()[1]->comment);
    }

    public function test_list_by_student_loads_author_relationship(): void
    {
        $student = User::factory()->student()->create();
        $author = User::factory()->admin()->create();

        StudentComment::factory()->create([
            'student_id' => $student->id,
            'author_id' => $author->id,
            'comment' => 'Test comment',
        ]);

        $result = $this->repository->listByStudent($student->id, 15);

        $this->assertTrue($result->items()[0]->relationLoaded('author'));
        $this->assertSame($author->id, $result->items()[0]->author->id);
    }

    public function test_count_by_student_returns_correct_count(): void
    {
        $student = User::factory()->student()->create();
        $author = User::factory()->admin()->create();

        StudentComment::factory()->count(3)->create([
            'student_id' => $student->id,
            'author_id' => $author->id,
        ]);

        // Create comment for different student
        $otherStudent = User::factory()->student()->create();
        StudentComment::factory()->create([
            'student_id' => $otherStudent->id,
            'author_id' => $author->id,
        ]);

        $count = $this->repository->countByStudent($student->id);

        $this->assertSame(3, $count);
    }
}
