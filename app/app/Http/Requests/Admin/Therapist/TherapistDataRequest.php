<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(UserStatus::values())],
            'filter_position_id' => ['nullable', 'integer', 'exists:positions,id'],
        ];
    }
}
