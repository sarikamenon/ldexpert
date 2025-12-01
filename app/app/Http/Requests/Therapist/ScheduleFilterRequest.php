<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class ScheduleFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
        ];
    }
}
