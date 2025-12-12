<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\RateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    public function rules(): array
    {
        $rules = [
            'student_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'ssa_id' => ['sometimes', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['sometimes', 'integer', Rule::exists('services', 'id')],
            'session_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after:start_time'],
            'notes' => ['sometimes', 'string', 'min:50', 'max:5000'],
            'is_billable_therapist' => ['sometimes', 'boolean'],
            'is_billable_school' => ['sometimes', 'boolean'],
            'is_rate_override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'min:20', 'max:500'],
        ];

        // Conditional validation for rate override
        if ($this->boolean('is_rate_override')) {
            $rules['override_reason'][] = 'required';
            $rules['therapist_rate_type'] = ['required', 'string', Rule::in(RateType::values())];
            $rules['therapist_rate_amount'] = ['required', 'numeric', 'min:0'];
            $rules['therapist_billable_amount'] = ['required', 'numeric', 'min:0'];
            // School-side overrides are admin-only; keep therapist UI focused on provider amounts.
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'notes.min' => 'Session notes must be at least :min characters.',
            'end_time.after' => 'End time must be after start time.',
            'override_reason.required' => 'Override reason is required when rate is overridden.',
            'override_reason.min' => 'Override reason must be at least :min characters.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $sessionLog = $this->route('sessionLog');

            if (! $sessionLog) {
                return;
            }

            // Validate session log is in draft status
            if (! $sessionLog->canEdit()) {
                $validator->errors()->add('status', 'Session log cannot be edited in its current status.');
            }
        });
    }
}
