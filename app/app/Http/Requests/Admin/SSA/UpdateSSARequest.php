<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use App\Enums\ServiceFrequency;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

        if ($this->input('frequency') === ServiceFrequency::ONE_TIME->value) {
            $frequency = ServiceFrequency::ONE_TIME;
            $normalizedValues = [
                'sessions_per_frequency' => $frequency->normalizeSessionsPerFrequency(null),
            ];

            if ($this->filled('minutes_per_session')) {
                $normalizedValues['calculated_minutes'] = $frequency->normalizeCalculatedMinutes(
                    (int) $this->input('minutes_per_session'),
                    null
                );
            }

            $this->merge($normalizedValues);
        }
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $frequencies = array_map(static fn (ServiceFrequency $freq) => $freq->value, ServiceFrequency::cases());

        return [
            'assigned_therapist_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'additional_service_ids' => ['nullable', 'array'],
            'additional_service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(function ($query) {
                    $query->where('is_direct_service', false);
                }),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
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
            'additional_notes' => ['nullable', 'string', 'max:65535'],
            'tho_minutes' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.after' => 'End date must be after start date.',
            'minutes_per_session.min' => 'Minutes per session must be at least 5 minutes.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateDateRange($validator);
        });
    }

    private function validateDateRange(Validator $validator): void
    {
        if (
            $validator->errors()->has('start_date') ||
            $validator->errors()->has('end_date')
        ) {
            return;
        }

        /** @var ServiceSupportAgreement|null $ssa */
        $ssa = $this->route('ssa');
        $startDateInput = $this->input('start_date');
        $endDateInput = $this->input('end_date');

        if ($startDateInput === null && $endDateInput === null) {
            return;
        }

        $resolvedStartDate = $startDateInput ?? $ssa?->start_date?->format('Y-m-d');
        $resolvedEndDate = $endDateInput ?? $ssa?->end_date?->format('Y-m-d');

        if ($resolvedStartDate === null || $resolvedEndDate === null) {
            return;
        }

        $frequency = ServiceFrequency::tryFrom((string) ($this->input('frequency') ?? $ssa?->frequency?->value));
        $startDate = Carbon::parse($resolvedStartDate)->startOfDay();
        $endDate = Carbon::parse($resolvedEndDate)->startOfDay();

        if ($frequency === ServiceFrequency::ONE_TIME) {
            if ($endDate->lt($startDate)) {
                $validator->errors()->add('end_date', 'End date must be the same as or after start date for one-time SSAs.');
            }

            return;
        }

        if (! $endDate->gt($startDate)) {
            $validator->errors()->add('end_date', 'End date must be after start date.');
        }
    }
}
