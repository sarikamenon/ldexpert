<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

use App\Enums\RateType;
use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TherapistContractFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function baseRules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('services', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', ServiceStatus::ACTIVE->value),
            ],
            'services.*.rate' => ['required', 'numeric', 'min:0'],
            'services.*.rate_type' => ['required', Rule::in(RateType::values())],
            'services.*.no_show_rate' => ['nullable', 'required_with:services.*.no_show_rate_type', 'numeric', 'min:0'],
            'services.*.no_show_rate_type' => ['nullable', 'required_with:services.*.no_show_rate', Rule::in(RateType::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'services.*.service_id.required' => 'Please select a service.',
            'services.*.service_id.distinct' => 'This service has already been added. Please select a different service.',
            'services.*.service_id.exists' => 'The selected service is invalid or no longer available.',
            'services.*.rate.required' => 'Rate is required.',
            'services.*.rate.numeric' => 'Rate must be a valid number.',
            'services.*.rate.min' => 'Rate must be 0 or greater.',
            'services.*.rate_type.required' => 'Rate type is required.',
            'services.*.no_show_rate.required_with' => 'No-show rate is required when no-show rate type is provided.',
            'services.*.no_show_rate.numeric' => 'No-show rate must be a valid number.',
            'services.*.no_show_rate.min' => 'No-show rate must be 0 or greater.',
            'services.*.no_show_rate_type.required_with' => 'No-show rate type is required when no-show rate is provided.',
        ];
    }
}
