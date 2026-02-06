<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'admin';
    }

    public function rules(): array
    {
        $rateTypes = RateType::values();

        $rules = [
            'therapist_rate_type' => ['nullable', Rule::in($rateTypes)],
            'therapist_rate_amount' => ['nullable', 'numeric', 'min:0'],
            'therapist_billable_amount' => ['nullable', 'numeric', 'min:0'],
            'school_rate_type' => ['nullable', Rule::in($rateTypes)],
            'school_rate_amount' => ['nullable', 'numeric', 'min:0'],
            'school_invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'is_rate_override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'min:20'],
        ];

        if ($this->boolean('is_rate_override')) {
            $rules['override_reason'][] = 'required';
            $rules['therapist_rate_type'][] = 'required';
            $rules['therapist_rate_amount'][] = 'required';
            $rules['therapist_billable_amount'][] = 'required';
            $rules['school_rate_type'][] = 'required';
            $rules['school_rate_amount'][] = 'required';
            $rules['school_invoice_amount'][] = 'required';
        }

        return $rules;
    }
}
