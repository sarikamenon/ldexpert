<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Enums\ServiceFrequency;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ServiceFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function baseRules(?int $serviceId = null): array
    {
        $nameRule = Rule::unique('services', 'name');
        if ($serviceId) {
            $nameRule = $nameRule->ignore($serviceId);
        }

        $frequencies = array_map(static fn(ServiceFrequency $frequency) => $frequency->value, ServiceFrequency::cases());
        $deliveryModes = array_keys(Service::deliveryModeOptions());

        return [
            'name' => ['required', 'string', 'max:255', $nameRule],
            'description' => ['nullable', 'string'],
            'direct_service' => ['required', 'boolean'],
            'group_service' => ['required', 'boolean'],
            'frequency' => ['required', Rule::in($frequencies)],
            'delivery_mode' => ['required', Rule::in($deliveryModes)],
            'is_billable' => ['required', 'boolean'],
            'min_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'max_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440', 'gte:min_duration_minutes'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_duration_minutes.gte' => 'Max duration must be greater than or equal to min duration.',
        ];
    }
}
