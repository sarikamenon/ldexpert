<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\Role;
use App\Models\SessionLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTherapistBillRequest extends FormRequest
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
        // If bill_number is empty, set it to null so validation allows it
        // The service will auto-generate it
        if ($this->has('bill_number') && empty(trim($this->input('bill_number')))) {
            $this->merge(['bill_number' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'therapist_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::THERAPIST->value)],
            'bill_number' => ['nullable', 'string', 'max:255', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('therapist_bills', 'bill_number')],
            'bill_date' => ['required', 'date'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'session_log_ids' => ['required', 'array', 'min:1'],
            'session_log_ids.*' => ['required', 'integer', Rule::exists('session_logs', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('therapist_id') && $this->has('session_log_ids')) {
                $therapistId = (int) $this->input('therapist_id');
                $sessionLogIds = $this->input('session_log_ids', []);

                if (! empty($sessionLogIds)) {
                    $invalidCount = SessionLog::query()
                        ->whereIn('id', $sessionLogIds)
                        ->where('therapist_id', '!=', $therapistId)
                        ->count();

                    if ($invalidCount > 0) {
                        $validator->errors()->add(
                            'session_log_ids',
                            'All selected session logs must belong to the selected therapist.'
                        );
                    }
                }
            }
        });
    }
}
