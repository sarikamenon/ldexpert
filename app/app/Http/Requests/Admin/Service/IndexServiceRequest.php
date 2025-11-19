<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $frequencies = array_map(static fn(ServiceFrequency $frequency) => $frequency->value, ServiceFrequency::cases());
        $statuses = array_map(static fn(ServiceStatus $status) => $status->value, ServiceStatus::cases());
        $deliveryModes = array_keys(Service::deliveryModeOptions());

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($statuses)],
            'frequency' => ['nullable', Rule::in($frequencies)],
            'direct_service' => ['nullable', 'boolean'],
            'group_service' => ['nullable', 'boolean'],
            'is_billable' => ['nullable', 'boolean'],
            'delivery_mode' => ['nullable', Rule::in($deliveryModes)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
