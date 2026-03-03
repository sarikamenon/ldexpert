<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports\SSA;

use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CaseloadReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $statuses = array_map(static fn (SSAStatus $status) => $status->value, SSAStatus::cases());

        return [
            'school_ids' => ['nullable', 'array'],
            'school_ids.*' => ['integer', Rule::exists('schools', 'id')],
            'therapist_ids' => ['nullable', 'array'],
            'therapist_ids.*' => ['integer', Rule::exists('users', 'id')],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'min_minutes_per_week' => ['nullable', 'integer', 'min:0'],
            'max_minutes_per_week' => ['nullable', 'integer', 'min:0', 'gte:min_minutes_per_week'],
        ];
    }
}
