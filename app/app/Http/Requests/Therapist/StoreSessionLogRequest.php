<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Enums\RateType;
use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    public function rules(): array
    {
        $rules = [
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'ssa_id' => ['required', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'schedule_id' => ['nullable', 'integer', Rule::exists('schedules', 'id')],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['required', 'date_format:Y-m-d H:i:s', 'after:start_time'],
            'notes' => ['required', 'string', 'min:50', 'max:5000'],
            'is_billable_therapist' => ['nullable', 'boolean'],
            'is_billable_school' => ['nullable', 'boolean'],
            'is_rate_override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'min:20', 'max:500'],
        ];

        // Conditional validation for rate override
        if ($this->boolean('is_rate_override')) {
            $rules['override_reason'][] = 'required';
            // Therapist override only in therapist UI; school-side override is reserved for admins.
            $rules['therapist_rate_type'] = ['required', 'string', Rule::in(RateType::values())];
            $rules['therapist_rate_amount'] = ['required', 'numeric', 'min:0'];
            $rules['therapist_billable_amount'] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Session notes are required.',
            'notes.min' => 'Session notes must be at least :min characters.',
            'ssa_id.required' => 'SSA is required.',
            'ssa_id.exists' => 'The selected SSA is invalid.',
            'student_id.required' => 'Student is required.',
            'service_id.required' => 'Service is required.',
            'session_date.required' => 'Session date is required.',
            'start_time.required' => 'Start time is required.',
            'end_time.required' => 'End time is required.',
            'end_time.after' => 'End time must be after start time.',
            'override_reason.required' => 'Override reason is required when rate is overridden.',
            'override_reason.min' => 'Override reason must be at least :min characters.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $therapist = $this->user();

            if (! $therapist) {
                return;
            }

            $repository = app(SessionLogRepositoryInterface::class);

            // Validate therapist has access to student
            $studentId = $this->input('student_id');
            if ($studentId && ! $repository->validateTherapistAccessToStudent($therapist, (int) $studentId)) {
                $validator->errors()->add('student_id', 'You do not have access to this student.');
            }

            // Validate therapist has access to SSA
            $ssaId = $this->input('ssa_id');
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
                if ($ssa->status !== SSAStatus::ACTIVE) {
                    $validator->errors()->add('ssa_id', 'You can only create session logs for active SSAs.');
                }

                // Validate student belongs to SSA
                if ($studentId && $ssa->student_id !== (int) $studentId) {
                    $validator->errors()->add('student_id', 'Student must belong to the selected SSA.');
                }
            }

            // Validate schedule if provided
            $scheduleId = $this->input('schedule_id');
            if ($scheduleId) {
                $schedule = \App\Models\Schedule::find($scheduleId);
                if ($schedule && $schedule->therapist_id !== $therapist->id) {
                    $validator->errors()->add('schedule_id', 'You do not have access to this schedule.');
                }
            }
        });
    }
}
