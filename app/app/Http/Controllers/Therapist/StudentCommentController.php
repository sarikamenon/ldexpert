<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentCommentService;
use App\DTOs\CreateStudentCommentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\Student\StoreStudentCommentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class StudentCommentController extends Controller
{
    public function __construct(
        private readonly StudentCommentService $commentService,
        private readonly SSAService $ssaService,
    ) {}

    public function store(StoreStudentCommentRequest $request, User $student): JsonResponse
    {
        $therapist = $request->user();

        // Verify therapist has access to this student
        if (! $this->ssaService->hasStudentAssignedToTherapist($student->id, $therapist->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this student.',
            ], 403);
        }

        $dto = CreateStudentCommentDTO::fromArray([
            'student_id' => $student->id,
            'author_id' => $therapist->id,
            'comment' => $request->validated()['comment'],
        ]);

        $comment = $this->commentService->create($dto);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'comment' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'author_name' => $comment->author->name,
                'author_role' => $comment->author->role->value,
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ]);
    }
}
