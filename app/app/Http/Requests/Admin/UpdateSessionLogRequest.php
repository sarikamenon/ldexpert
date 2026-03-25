<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RateType;
use App\Models\SessionLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'admin';
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sessionLog = $this->route('sessionLog');
            if (! $sessionLog instanceof SessionLog) {
                return;
            }

            if ($sessionLog->isAttachedToInvoiceOrTherapistBill()) {
                $validator->errors()->add('rate_override', SessionLog::RATE_OVERRIDE_BLOCKED_MESSAGE);
            }
        });
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $rateTypes = RateType::values();

        // Admin session log update is only used for the override page; override fields are always required.
        return [
            'therapist_rate_type' => ['required', Rule::in($rateTypes)],
            'therapist_rate_amount' => ['required', 'numeric', 'min:0'],
            'therapist_billable_amount' => ['required', 'numeric', 'min:0'],
            'school_rate_type' => ['required', Rule::in($rateTypes)],
            'school_rate_amount' => ['required', 'numeric', 'min:0'],
            'school_invoice_amount' => ['required', 'numeric', 'min:0'],
            'is_rate_override' => ['sometimes', 'boolean'],
            'override_reason' => ['required', 'string', 'min:20'],
        ];
    }
}
