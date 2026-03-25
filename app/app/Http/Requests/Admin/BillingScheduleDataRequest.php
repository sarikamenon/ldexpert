<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BillingScheduleDataRequest extends FormRequest
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
            'filter_schedule_type' => ['nullable', Rule::in(BillingScheduleType::values())],
            'filter_billing_mode' => ['nullable', Rule::in(BillingMode::values())],
            'filter_is_active' => ['nullable', 'in:0,1'],
            'filter_frequency' => ['nullable', 'string', 'max:30'],
        ];
    }
}
