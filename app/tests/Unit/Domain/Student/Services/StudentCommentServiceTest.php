<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentCommentRepositoryInterface;
use App\Domain\Student\Services\StudentCommentService;
use App\DTOs\CreateStudentCommentDTO;
use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

final class StudentCommentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentCommentService $service;

    private StudentCommentRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(StudentCommentRepositoryInterface::class);
        $this->service = new StudentCommentService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_delegates_to_repository(): void
    {
        $student = User::factory()->student()->create();
        $author = User::factory()->admin()->create();
        $comment = StudentComment::factory()->make([
            'student_id' => $student->id,
            'author_id' => $author->id,
        ]);

        $dto = new CreateStudentCommentDTO(
            studentId: $student->id,
            authorId: $author->id,
            comment: 'Test comment',
        );

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($dto) {
                return $arg->studentId === $dto->studentId
                    && $arg->authorId === $dto->authorId
                    && $arg->comment === $dto->comment;
            }))
            ->andReturn($comment);

        $result = $this->service->create($dto);

        $this->assertInstanceOf(StudentComment::class, $result);
    }

    public function test_list_by_student_delegates_to_repository(): void
    {
        $student = User::factory()->student()->create();
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('listByStudent')
            ->once()
            ->with($student->id, 15)
            ->andReturn($paginator);

        $result = $this->service->listByStudent($student->id);

        $this->assertSame($paginator, $result);
    }

    public function test_list_by_student_uses_custom_per_page(): void
    {
        $student = User::factory()->student()->create();
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('listByStudent')
            ->once()
            ->with($student->id, 20)
            ->andReturn($paginator);

        $result = $this->service->listByStudent($student->id, 20);

        $this->assertSame($paginator, $result);
    }
}
