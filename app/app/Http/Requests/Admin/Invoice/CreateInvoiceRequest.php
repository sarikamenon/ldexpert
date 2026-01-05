<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Invoice;

use App\Models\SessionLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If invoice_number is empty, set it to null so validation allows it
        // The service will auto-generate it
        if ($this->has('invoice_number') && empty(trim($this->input('invoice_number')))) {
            $this->merge(['invoice_number' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'invoice_number' => ['nullable', 'string', 'max:255', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('invoices', 'invoice_number')],
            'invoice_date' => ['required', 'date'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
            'session_log_ids' => ['required', 'array', 'min:1'],
            'session_log_ids.*' => ['required', 'integer', Rule::exists('session_logs', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('school_id') && $this->has('session_log_ids')) {
                $schoolId = (int) $this->input('school_id');
                $sessionLogIds = $this->input('session_log_ids', []);

                if (!empty($sessionLogIds)) {
                    $invalidCount = SessionLog::query()
                        ->whereIn('id', $sessionLogIds)
                        ->where('school_id', '!=', $schoolId)
                        ->count();

                    if ($invalidCount > 0) {
                        $validator->errors()->add(
                            'session_log_ids',
                            'All selected session logs must belong to the selected school.'
                        );
                    }
                }
            }
        });
    }
}
