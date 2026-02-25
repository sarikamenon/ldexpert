<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistStudentDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'therapist';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(UserStatus::values())],
        ];
    }
}
