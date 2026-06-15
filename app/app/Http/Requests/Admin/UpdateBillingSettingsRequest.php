<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BillingFrequency;
use App\Enums\GenerationDayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBillingSettingsRequest extends FormRequest
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
            'default_frequency' => ['required', Rule::in(BillingFrequency::values())],
            'default_generation_day_type' => ['required', Rule::in(GenerationDayType::values())],
            'default_generation_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'default_delay_days' => ['required', 'integer', 'min:0', 'max:30'],
            'default_payment_terms_days' => ['required', 'integer', 'min:1', 'max:90'],
            'default_auto_generate' => ['boolean'],
            'default_auto_send' => ['boolean'],
            'advance_default_frequency' => ['required', Rule::in(BillingFrequency::values())],
            'advance_default_generation_day_type' => ['required', Rule::in(GenerationDayType::values())],
            'advance_default_generation_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'advance_default_delay_days' => ['required', 'integer', 'min:0', 'max:30'],
            'advance_default_payment_terms_days' => ['required', 'integer', 'min:0', 'max:90'],
            'advance_default_auto_generate' => ['boolean'],
            'advance_default_auto_send' => ['boolean'],
            'standard_default_frequency' => ['required', Rule::in(BillingFrequency::values())],
            'standard_default_generation_day_type' => ['required', Rule::in(GenerationDayType::values())],
            'standard_default_generation_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'standard_default_delay_days' => ['required', 'integer', 'min:0', 'max:30'],
            'standard_default_payment_terms_days' => ['required', 'integer', 'min:1', 'max:90'],
            'standard_default_auto_generate' => ['boolean'],
            'standard_default_auto_send' => ['boolean'],
            'reminder_days_before_due' => ['required', 'integer', 'min:1', 'max:30'],
            'reminder_days_after_due' => ['required', 'integer', 'min:1', 'max:30'],
            'reminder_overdue_repeat_days' => ['required', 'integer', 'min:1', 'max:30'],
            'max_overdue_reminders' => ['required', 'integer', 'min:0', 'max:10'],
        ];
    }
}
