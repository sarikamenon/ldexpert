<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use App\Enums\ServiceFrequency;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SSAFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('additional_service_ids')) {
            $this->merge([
                'additional_service_ids' => [],
            ]);
        }
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function baseRules(): array
    {
        $frequencies = array_map(static fn (ServiceFrequency $freq) => $freq->value, ServiceFrequency::cases());

        return [
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('role', 'student');
            })],
            'primary_service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'additional_service_ids' => ['nullable', 'array'],
            'additional_service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(function ($query) {
                    $query->where('is_direct_service', false);
                }),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'minutes_per_session' => [
                'required',
                'integer',
                'min:'.config('session_minutes.min'),
                'max:'.config('session_minutes.max'),
            ],
            'frequency' => ['nullable', Rule::in($frequencies)],
            'sessions_per_frequency' => ['nullable', 'integer', 'min:1', 'max:100'],
            'calculated_minutes' => ['nullable', 'integer', 'min:0'],
            'adjusted_minutes' => ['nullable', 'integer'],
            'adjustment_notes' => ['nullable', 'string', 'max:65535'],
            'tho_minutes' => ['required', 'numeric', 'min:0'],
            'assigned_therapist_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'therapist');
                }),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.after' => 'End date must be after start date.',
            'minutes_per_session.min' => 'Minutes per session must be at least 5 minutes.',
            'assigned_therapist_id.exists' => 'Selected therapist must be an active therapist.',
            'additional_service_ids.*.distinct' => 'Duplicate additional services are not allowed.',
            'additional_service_ids.*.exists' => 'Additional services must be indirect services from the catalog.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $primaryServiceId = $this->input('primary_service_id');

            if ($primaryServiceId) {
                /** @var Service|null $service */
                $service = Service::find($primaryServiceId);

                if ($service && $service->is_frequency_service) {
                    // Service supports frequency, so frequency and sessions_per_frequency are required
                    if (! $this->filled('frequency')) {
                        $validator->errors()->add('frequency', 'The frequency field is required when the service supports frequency.');
                    }
                    if (! $this->filled('sessions_per_frequency')) {
                        $validator->errors()->add('sessions_per_frequency', 'The sessions per frequency field is required when the service supports frequency.');
                    }
                }
            }
        });
    }
}
