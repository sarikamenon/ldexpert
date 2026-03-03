<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(UserStatus::values())],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
