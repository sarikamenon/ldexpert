<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Student\Services\StudentCommentService;
use App\DTOs\CreateStudentCommentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\StoreStudentCommentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class StudentCommentController extends Controller
{
    public function __construct(
        private readonly StudentCommentService $commentService,
    ) {}

    public function store(StoreStudentCommentRequest $request, User $student): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $dto = CreateStudentCommentDTO::fromArray([
            'student_id' => $student->id,
            'author_id' => $user->id,
            'comment' => $request->validated()['comment'],
        ]);

        $comment = $this->commentService->create($dto);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'comment' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'author_name' => $comment->author?->name,
                'author_role' => $comment->author?->role?->value,
                'created_at' => $comment->created_at?->toIso8601String(),
            ],
        ]);
    }
}
