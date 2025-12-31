<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
            'session_log_ids' => ['required', 'array', 'min:1'],
            'session_log_ids.*' => ['required', 'integer', Rule::exists('session_logs', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
