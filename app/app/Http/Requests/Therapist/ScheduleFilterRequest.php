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
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'ssa_id' => ['nullable', 'integer', 'exists:service_support_agreements,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
