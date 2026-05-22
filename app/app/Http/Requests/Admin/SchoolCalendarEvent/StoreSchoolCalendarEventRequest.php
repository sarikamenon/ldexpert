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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_makeup' => $this->boolean('request_makeup'),
        ]);
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'string', Rule::in(SchoolCalendarEventType::options())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'request_makeup' => ['required', 'boolean'],
            'reminder_date' => ['exclude_unless:request_makeup,true', 'required', 'date', 'before:response_date'],
            'response_date' => ['exclude_unless:request_makeup,true', 'required', 'date', 'after:reminder_date', 'before_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'event_type.required' => 'Event type is required.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'reminder_date.required' => 'Email send date is required when Request Makeup Session is enabled.',
            'reminder_date.before' => 'Email send date must be before the response date.',
            'response_date.required' => 'Response date is required when Request Makeup Session is enabled.',
            'response_date.after' => 'Response date must be after the email send date.',
            'response_date.before_or_equal' => 'Response date must be on or before the start date.',
        ];
    }
}
