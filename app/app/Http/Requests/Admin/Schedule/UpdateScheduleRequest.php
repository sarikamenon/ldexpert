<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Schedule;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\WeekDay;
use App\Http\Requests\Concerns\ValidatesOccurrenceTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateScheduleRequest extends FormRequest
{
    use ValidatesOccurrenceTimes;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $recurrenceTypes = collect(RecurrenceType::cases())
            ->map(static fn (RecurrenceType $type): string => $type->value)
            ->all();

        $billingStatuses = collect(BillingStatus::cases())
            ->map(static fn (BillingStatus $status): string => $status->value)
            ->all();

        $weekDayValues = collect(WeekDay::cases())->pluck('value')->all();

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
            'weekly_days.*' => ['string', Rule::in($weekDayValues)],
            'occurrence_dates' => ['nullable', 'array'],
            'occurrence_dates.*' => ['required', 'date'],
            'occurrence_start_times' => ['nullable', 'array'],
            'occurrence_start_times.*' => ['required', 'date_format:H:i'],
            'occurrence_end_times' => ['nullable', 'array'],
            'occurrence_end_times.*' => ['required', 'date_format:H:i'],
            'occurrences_regenerated' => ['nullable', 'boolean'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOccurrenceTimeRules($validator);
        });
    }
}
