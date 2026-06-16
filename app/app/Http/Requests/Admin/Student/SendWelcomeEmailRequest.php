<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendWelcomeEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role', Role::STUDENT->value),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'student_ids.required' => 'Select at least one student.',
            'student_ids.*.exists' => 'One or more selected students are invalid.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function studentIds(): array
    {
        /** @var array<int, int> $ids */
        $ids = $this->validated('student_ids');

        return $ids;
    }
}
