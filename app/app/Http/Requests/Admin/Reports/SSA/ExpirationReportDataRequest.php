<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports\SSA;

use Illuminate\Foundation\Http\FormRequest;

final class ExpirationReportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter_expiration_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'filter_school_ids' => ['nullable', 'array'],
            'filter_school_ids.*' => ['integer', 'exists:schools,id'],
            'filter_therapist_ids' => ['nullable', 'array'],
            'filter_therapist_ids.*' => ['integer', 'exists:users,id'],
            'filter_bucket' => ['nullable', 'string', 'in:upcoming,expired,pending'],
        ];
    }
}
