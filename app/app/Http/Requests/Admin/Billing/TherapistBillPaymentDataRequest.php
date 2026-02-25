<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistBillPaymentDataRequest extends FormRequest
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
            'filter_from_date' => ['nullable', 'date'],
            'filter_to_date' => ['nullable', 'date', 'after_or_equal:filter_from_date'],
            'filter_method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'filter_search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
