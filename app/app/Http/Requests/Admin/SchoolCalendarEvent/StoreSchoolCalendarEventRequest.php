<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SchoolCalendarEvent;

use App\Enums\Role;
use App\Enums\SchoolCalendarEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSchoolCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->user();

        return $user->role === Role::ADMIN;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'string', Rule::in(SchoolCalendarEventType::options())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'event_type.required' => 'Event type is required.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
