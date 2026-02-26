<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StudentDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(UserStatus::values())],
            'filter_school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'filter_therapist_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
