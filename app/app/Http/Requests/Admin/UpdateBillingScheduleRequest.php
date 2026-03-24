<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\GenerationDayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBillingScheduleRequest extends FormRequest
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
            'schedulable_type' => ['required', 'string', 'in:App\\Models\\School,App\\Models\\User'],
            'schedulable_id' => ['required', 'integer', 'min:1'],
            'schedule_type' => ['required', Rule::in(BillingScheduleType::values())],
            'billing_mode' => ['required', Rule::in(BillingMode::values())],
            'frequency' => ['required', Rule::in(BillingFrequency::values())],
            'generation_day_type' => ['required', Rule::in(GenerationDayType::values())],
            'generation_day_of_week' => ['nullable', 'required_if:generation_day_type,day_of_week', 'integer', 'min:0', 'max:6'],
            'generation_delay_days' => ['nullable', 'required_if:generation_day_type,fixed_delay', 'integer', 'min:1', 'max:30'],
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

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $type = $this->input('schedulable_type');
            $id = (int) $this->input('schedulable_id');
            $scheduleType = $this->input('schedule_type');
            $currentSchedule = $this->route('schedule');

            if ($type && $id && $scheduleType && $currentSchedule instanceof \App\Models\BillingSchedule) {
                $existing = \App\Models\BillingSchedule::query()
                    ->where('schedulable_type', $type)
                    ->where('schedulable_id', $id)
                    ->where('schedule_type', $scheduleType)
                    ->where('id', '!=', $currentSchedule->id)
                    ->exists();

                if ($existing) {
                    $v->errors()->add('schedulable_id', 'A billing schedule already exists for this entity and type.');
                }
            }
        });
    }
}
