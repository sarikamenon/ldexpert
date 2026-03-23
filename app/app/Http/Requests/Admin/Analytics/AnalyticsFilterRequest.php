<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Analytics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin';
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'date_range' => ['nullable', 'string', Rule::in([
                'last_7_days',
                'last_30_days',
                'last_90_days',
                'this_month',
                'last_month',
                'this_year',
                'custom',
            ])],
            'start_date' => ['nullable', 'required_if:date_range,custom', 'date'],
            'end_date' => ['nullable', 'required_if:date_range,custom', 'date', 'after_or_equal:start_date'],
        ];
    }
}
