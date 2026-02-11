<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist\Student;

use App\Domain\SSA\Services\SSAService;
use App\Models\StudentComment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStudentCommentRequest extends FormRequest
{
    public function __construct(
        private readonly SSAService $ssaService,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $student = $this->route('student');
        $therapist = $this->user();

        // Verify therapist has access to this student
        if (! $this->ssaService->hasStudentAssignedToTherapist($student->id, $therapist->id)) {
            return false;
        }

        return $therapist->can('create', [StudentComment::class, $student]);
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
