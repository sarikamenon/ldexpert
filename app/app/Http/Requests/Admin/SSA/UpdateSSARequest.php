<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use App\Enums\ServiceFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSSARequest extends FormRequest
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

    public function rules(): array
    {
        $frequencies = array_map(static fn (ServiceFrequency $freq) => $freq->value, ServiceFrequency::cases());

        return [
            'additional_service_ids' => ['nullable', 'array'],
            'additional_service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(function ($query) {
                    $query->where('is_direct_service', false);
                }),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'minutes_per_session' => [
                'nullable',
                'integer',
                'min:'.config('session_minutes.min'),
                'max:'.config('session_minutes.max'),
            ],
            'frequency' => ['nullable', Rule::in($frequencies)],
            'sessions_per_frequency' => ['nullable', 'integer', 'min:1', 'max:100'],
            'calculated_minutes' => ['nullable', 'integer', 'min:0'],
            'adjusted_minutes' => ['nullable', 'integer'],
            'adjustment_notes' => ['nullable', 'string', 'max:65535'],
            'tho_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after' => 'End date must be after start date.',
            'minutes_per_session.min' => 'Minutes per session must be at least 5 minutes.',
        ];
    }
}
