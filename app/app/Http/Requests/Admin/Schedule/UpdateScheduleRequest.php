<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Schedule;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\WeekDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $recurrenceTypes = array_map(
            static fn (RecurrenceType $type): string => $type->value,
            RecurrenceType::cases()
        );

        $billingStatuses = array_map(
            static fn (BillingStatus $status): string => $status->value,
            BillingStatus::cases()
        );

        return [
            'schedule_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.config('session_minutes.min'),
                'max:'.config('session_minutes.max'),
            ],
            'recurrence_type' => ['nullable', Rule::in($recurrenceTypes)],
            'recurrence_end_date' => ['nullable', 'date', 'after:schedule_date'],
            'weekly_days' => ['nullable', 'array'],
            'weekly_days.*' => ['string', Rule::in(array_column(WeekDay::cases(), 'value'))],
            'occurrence_dates' => ['nullable', 'array'],
            'occurrence_dates.*' => ['required', 'date'],
            'location_details' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'billing_status' => ['nullable', Rule::in($billingStatuses)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_minutes.min' => 'Duration must be at least :min minutes.',
            'duration_minutes.max' => 'Duration may not be greater than :max minutes.',
            'schedule_date.after_or_equal' => 'Schedule date cannot be in the past.',
            'recurrence_end_date.after' => 'Recurrence end date must be after schedule date.',
        ];
    }
}
