<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $statuses = array_map(static fn (ServiceStatus $status) => $status->value, ServiceStatus::cases());

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($statuses)],
            'is_frequency_service' => ['nullable', 'boolean'],
            'is_direct_service' => ['nullable', 'boolean'],
            'is_group_service' => ['nullable', 'boolean'],
            'is_billable' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
