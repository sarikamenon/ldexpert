<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ServiceDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(ServiceStatus::options())],
            'filter_is_frequency_service' => ['nullable', 'boolean'],
            'filter_is_direct_service' => ['nullable', 'boolean'],
            'filter_is_billable' => ['nullable', 'boolean'],
        ];
    }
}
