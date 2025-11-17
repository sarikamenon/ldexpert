<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeTherapistStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(UserStatus::values())],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

