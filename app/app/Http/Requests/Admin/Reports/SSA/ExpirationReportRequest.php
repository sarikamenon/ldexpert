<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports\SSA;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExpirationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expiration_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'school_ids' => ['nullable', 'array'],
            'school_ids.*' => ['integer', Rule::exists('schools', 'id')],
            'therapist_ids' => ['nullable', 'array'],
            'therapist_ids.*' => ['integer', Rule::exists('users', 'id')],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')],
            'bucket' => ['nullable', 'string', Rule::in(['upcoming', 'expired', 'pending', 'no_current'])],
        ];
    }
}
