<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTherapistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(UserStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

