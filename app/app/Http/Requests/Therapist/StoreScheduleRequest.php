<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\RecurrenceType;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    public function rules(): array
    {
        $recurrenceTypes = array_map(
            static fn (RecurrenceType $type): string => $type->value,
            RecurrenceType::cases()
        );

        return [
            'ssa_id' => ['required', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'student_ids' => ['required', 'array', 'min:1', 'max:1'], // Single student only for first iteration
            'student_ids.*' => ['required', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('role', 'student');
            })],
            'schedule_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:5,400', 'multiple_of:5'],
            'recurrence_type' => ['required', Rule::in($recurrenceTypes)],
            'recurrence_end_date' => ['required_unless:recurrence_type,'.RecurrenceType::NONE->value, 'nullable', 'date', 'after:schedule_date'],
            'occurrence_dates' => ['required_unless:recurrence_type,'.RecurrenceType::NONE->value, 'nullable', 'array', 'min:1'],
            'occurrence_dates.*' => ['required', 'date', 'after_or_equal:schedule_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'location_details' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration_minutes.required' => 'Duration is required.',
            'duration_minutes.between' => 'Duration must be between :min and :max minutes.',
            'duration_minutes.multiple_of' => 'Duration must be in 5-minute increments.',
            'schedule_date.after_or_equal' => 'Schedule date cannot be in the past.',
            'location_details.required' => 'Please enter the location or meeting details for this session.',
            'location_details.max' => 'Location/meeting details may not be greater than :max characters.',
            'recurrence_end_date.required_unless' => 'End date is required for recurring schedules.',
            'recurrence_end_date.after' => 'End date must be after the schedule start date.',
            'occurrence_dates.required_unless' => 'Occurrence dates are required for recurring schedules.',
            'occurrence_dates.array' => 'Occurrence dates must be an array.',
            'occurrence_dates.min' => 'At least one occurrence date is required.',
            'occurrence_dates.*.required' => 'All occurrence dates must be filled.',
            'occurrence_dates.*.date' => 'Each occurrence date must be a valid date.',
            'occurrence_dates.*.after_or_equal' => 'Each occurrence date must be on or after the schedule start date.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $therapist = $this->user();
            $ssaId = $this->input('ssa_id');
            $serviceId = $this->input('service_id');
            $studentIds = $this->input('student_ids', []);
            $studentIdsArray = is_array($studentIds) ? array_map('intval', $studentIds) : [];
            $studentCount = count($studentIdsArray);

            if (! $therapist) {
                return;
            }

            $repository = app(ScheduleRepositoryInterface::class);

            // Validate therapist has access to SSA and it's active
            if ($ssaId) {
                $ssa = \App\Models\ServiceSupportAgreement::find($ssaId);
                if (! $ssa) {
                    $validator->errors()->add('ssa_id', 'SSA not found.');

                    return;
                }

                if (! $repository->validateTherapistAccessToSSA($therapist, (int) $ssaId)) {
                    $validator->errors()->add('ssa_id', 'You do not have access to this SSA.');
                }

                // Validate SSA is active
                if ($ssa->status !== \App\Enums\SSAStatus::ACTIVE) {
                    $validator->errors()->add('ssa_id', 'You can only create schedules for active SSAs.');
                }
            }

            // Validate students belong to SSA if provided
            if ($ssaId && $studentCount > 0) {
                $ssa = \App\Models\ServiceSupportAgreement::find($ssaId);
                if ($ssa) {
                    foreach ($studentIdsArray as $studentId) {
                        if ($ssa->student_id !== (int) $studentId) {
                            $validator->errors()->add('student_ids', 'All students must belong to the selected SSA.');
                            break;
                        }
                    }
                }
            }

            // Validate service is available for the student via the SSA
            if ($serviceId && $ssaId && $studentCount > 0) {
                $ssa = \App\Models\ServiceSupportAgreement::find($ssaId);
                if ($ssa && $ssa->primary_service_id !== (int) $serviceId) {
                    // Check if it's an additional service
                    $isAdditionalService = $ssa->additionalServices()->where('services.id', $serviceId)->exists();
                    if (! $isAdditionalService) {
                        $validator->errors()->add('service_id', 'This service is not available for the selected SSA.');
                    }
                }
            }

            // Validate students are assigned to therapist
            if ($studentCount > 0) {
                if (! $repository->validateTherapistAccessToStudents($therapist, $studentIdsArray)) {
                    $validator->errors()->add('student_ids', 'One or more students are not assigned to you.');
                }
            }

            // Validate occurrence dates for recurring schedules
            $recurrenceType = $this->input('recurrence_type');
            $occurrenceDates = $this->input('occurrence_dates', []);

            if ($recurrenceType && $recurrenceType !== RecurrenceType::NONE->value && is_array($occurrenceDates) && count($occurrenceDates) > 0) {
                // Check for weekend dates
                $weekendDates = [];
                foreach ($occurrenceDates as $index => $dateStr) {
                    if ($dateStr) {
                        try {
                            $date = Carbon::parse($dateStr);
                            if ($date->isWeekend()) {
                                $weekendDates[] = $date->format('M d, Y');
                            }
                        } catch (\Exception $e) {
                            // Invalid date will be caught by validation rules
                        }
                    }
                }

                if (count($weekendDates) > 0) {
                    $validator->errors()->add('occurrence_dates', 'The following dates fall on weekends and cannot be scheduled: '.implode(', ', $weekendDates).'. Please adjust these dates.');
                }

                // Check for duplicate dates
                $uniqueDates = array_unique($occurrenceDates);
                if (count($uniqueDates) !== count($occurrenceDates)) {
                    $validator->errors()->add('occurrence_dates', 'Duplicate occurrence dates are not allowed. Each occurrence must be on a unique date.');
                }
            }
        });
    }
}
