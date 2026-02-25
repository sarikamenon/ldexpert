<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Models\StudentComment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStudentCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->user();
        $student = $this->route('student');

        return $user->can('create', [StudentComment::class, $student]);
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
