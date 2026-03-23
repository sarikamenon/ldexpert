<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\TherapistBillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistBillDataRequest extends FormRequest
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
            'filter_therapist_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter_status' => ['nullable', Rule::in(TherapistBillStatus::values())],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
            'filter_bill_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
