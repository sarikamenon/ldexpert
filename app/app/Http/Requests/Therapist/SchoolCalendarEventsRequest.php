<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class SchoolCalendarEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'therapist';
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ];
    }
}
