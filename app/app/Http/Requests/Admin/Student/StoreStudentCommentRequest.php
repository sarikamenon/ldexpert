<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Models\StudentComment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStudentCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $this->user()->can('create', [StudentComment::class, $student]);
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
