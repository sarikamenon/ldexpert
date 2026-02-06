<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports\SSA;

use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UtilizationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(static fn (SSAStatus $status) => $status->value, SSAStatus::cases());

        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'school_ids' => ['nullable', 'array'],
            'school_ids.*' => ['integer', Rule::exists('schools', 'id')],
            'therapist_ids' => ['nullable', 'array'],
            'therapist_ids.*' => ['integer', Rule::exists('users', 'id')],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', Rule::in($statuses)],
            'utilization_band' => ['nullable', 'string', Rule::in(['under_50', '50_80', '80_120', 'over_120'])],
            'grade_level' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
