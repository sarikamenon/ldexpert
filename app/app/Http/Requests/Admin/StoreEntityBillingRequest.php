<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\GenerationDayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEntityBillingRequest extends FormRequest
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
            'entity_type' => ['required', 'string', 'in:school,therapist'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'billing_mode' => ['required', Rule::in(BillingMode::values())],
            'frequency' => ['required', Rule::in(BillingFrequency::values())],
            'generation_day_type' => ['required', Rule::in(GenerationDayType::values())],
            'generation_day_of_week' => ['nullable', 'required_if:generation_day_type,day_of_week', 'integer', 'min:0', 'max:6'],
            'generation_delay_days' => ['nullable', 'required_if:generation_day_type,fixed_delay', 'integer', 'min:0', 'max:30'],
            'min_grace_days' => ['nullable', 'integer', 'min:0', 'max:14'],
            'payment_terms_days' => ['required', 'integer', 'min:1', 'max:90'],
            'auto_generate' => ['boolean'],
            'auto_send' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'billing_start_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'generation_day_of_week.required_if' => 'Day of week is required when generation type is "Day of Week".',
            'generation_delay_days.required_if' => 'Delay days is required when generation type is "Fixed Delay".',
        ];
    }
}
