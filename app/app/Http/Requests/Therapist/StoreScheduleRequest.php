<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\RecurrenceType;
use App\Models\Service;
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
            static fn(RecurrenceType $type): string => $type->value,
            RecurrenceType::cases()
        );

        return [
            'ssa_id' => ['nullable', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('role', 'student');
            })],
            'schedule_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'recurrence_type' => ['required', Rule::in($recurrenceTypes)],
            'occurrence_count' => ['nullable', 'integer', 'min:2'],
            'recurrence_end_date' => ['nullable', 'date', 'after:schedule_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'End time must be after start time.',
            'schedule_date.after_or_equal' => 'Schedule date cannot be in the past.',
            'recurrence_end_date.after' => 'Recurrence end date must be after schedule date.',
            'recurrence_end_date.required_if' => 'Recurrence end date is required when recurrence type is not none.',
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

            // Validate therapist has access to SSA if provided
            if ($ssaId) {
                if (! $repository->validateTherapistAccessToSSA($therapist, (int) $ssaId)) {
                    $validator->errors()->add('ssa_id', 'You do not have access to this SSA.');
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

            // Validate service allows multiple students
            if ($serviceId) {
                $service = Service::find($serviceId);
                if ($service) {
                    if (! $service->is_group_service && $studentCount > 1) {
                        $validator->errors()->add('student_ids', 'This service does not allow multiple students.');
                    }

                    if (
                        $studentCount > 1
                        && ! $repository->validateStudentsShareService($therapist, $studentIdsArray, (int) $serviceId)
                    ) {
                        $validator->errors()->add('service_id', 'Selected students do not share this service via an active SSA.');
                    }
                }
            }

            // Validate students are assigned to therapist
            if ($studentCount > 0) {
                if (! $repository->validateTherapistAccessToStudents($therapist, $studentIdsArray)) {
                    $validator->errors()->add('student_ids', 'One or more students are not assigned to you.');
                }
            }
            $recurrenceType = $this->input('recurrence_type');
            $occurrenceCount = (int) $this->input('occurrence_count');
            if ($recurrenceType && $recurrenceType !== RecurrenceType::NONE->value) {
                if ($occurrenceCount < 2) {
                    $validator->errors()->add('occurrence_count', 'Provide at least 2 occurrences for repeating schedules.');
                }
            }
        });
    }
}
