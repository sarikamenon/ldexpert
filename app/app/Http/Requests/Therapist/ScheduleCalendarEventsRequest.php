<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class ScheduleCalendarEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'therapist';
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'status' => ['nullable', 'string'],
            'billing_status' => ['nullable', 'string'],
        ];
    }
}
