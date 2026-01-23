<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Enums\SessionOutcome;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSessionLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Normalize notes by trimming leading/trailing whitespace
        if ($this->has('notes')) {
            $this->merge([
                'notes' => trim((string) $this->input('notes')),
            ]);
        }

        // When SSA is selected but student is not explicitly provided, infer student from SSA.
        $ssaId = $this->input('ssa_id');
        if ($ssaId && ! $this->filled('student_id')) {
            $ssa = ServiceSupportAgreement::find($ssaId);

            if ($ssa) {
                $this->merge([
                    'student_id' => $ssa->student_id,
                ]);
            }
        }

        $sessionDate = $this->input('session_date');
        $startTimeInput = $this->input('start_time');
        $endTimeInput = $this->input('end_time');
        $durationInput = $this->input('duration_minutes');

        // Normalize start/end time to full datetime (Y-m-d H:i:s) using session_date when only a time is provided.
        if ($sessionDate && $startTimeInput && $durationInput && ! str_contains((string) $startTimeInput, ' ')) {
            $start = Carbon::parse($sessionDate.' '.$startTimeInput.':00');
            $end = (clone $start)->addMinutes((int) $durationInput);

            $this->merge([
                'start_time' => $start->format('Y-m-d H:i:s'),
                'end_time' => $end->format('Y-m-d H:i:s'),
            ]);

            $startTimeInput = $this->input('start_time');
            $endTimeInput = $this->input('end_time');
        }

        // Fallback: if we already have full start/end datetimes, compute duration.
        $start = $startTimeInput;
        $end = $endTimeInput;

        if ($start && $end) {
            $startTime = Carbon::parse($start);
            $endTime = Carbon::parse($end);
            $durationMinutes = (int) round($startTime->diffInMinutes($endTime) / 5) * 5;

            $this->merge([
                'duration_minutes' => $durationMinutes,
            ]);
        }
    }

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
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['required', 'date_format:Y-m-d H:i:s', 'after:start_time'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'outcome' => ['string', Rule::in(SessionOutcome::values())],
            'notes' => ['required', 'string', 'min:50', 'max:5000'],
            'is_billable_therapist' => ['nullable', 'boolean'],
            'is_billable_school' => ['nullable', 'boolean'],
            // Therapists cannot override rates; this is reserved for admins.
            'is_rate_override' => ['prohibited'],
        ];

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
            'is_rate_override.prohibited' => 'Rate overrides are only allowed for admin users.',
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

                // Validate session date is within SSA range
                $sessionDate = $this->input('session_date');
                if ($sessionDate) {
                    $session = Carbon::parse($sessionDate);
                    if ($session->lt($ssa->start_date) || $session->gt($ssa->end_date)) {
                        $validator->errors()->add('session_date', 'Session date must fall within the SSA start and end dates.');
                    }
                }

                // Validate student belongs to SSA
                if ($studentId && $ssa->student_id !== (int) $studentId) {
                    $validator->errors()->add('student_id', 'Student must belong to the selected SSA.');
                }
            }

            // Validate schedule if provided
            $scheduleId = $this->input('schedule_id');
            if ($scheduleId) {
                $schedule = Schedule::find($scheduleId);
                if ($schedule && $schedule->therapist_id !== $therapist->id) {
                    $validator->errors()->add('schedule_id', 'You do not have access to this schedule.');
                }
            }

            // Validate session date is not a holiday
            $calendarService = app(SchoolCalendarService::class);
            $studentRepository = app(StudentRepositoryInterface::class);
            $schoolId = null;

            if ($scheduleId) {
                $schedule = Schedule::find($scheduleId);
                $schoolId = $schedule?->school_id
                    ?? ($schedule?->student_id ? $studentRepository->getSchoolIdByUserId((int) $schedule->student_id) : null);
            }

            if (! $schoolId) {
                $schoolId = $this->input('school_id')
                    ? (int) $this->input('school_id')
                    : null;
            }

            if (! $schoolId && $studentId) {
                $schoolId = $studentRepository->getSchoolIdByUserId((int) $studentId);
            }

            $sessionDate = $this->input('session_date');
            if ($schoolId && $sessionDate) {
                $date = Carbon::parse((string) $sessionDate);
                if ($calendarService->isHolidayDate((int) $schoolId, $date)) {
                    $validator->errors()->add('session_date', 'Session date falls on a school holiday.');
                }
            }

            // Validate duration within service limits (if available)
            $serviceId = $this->input('service_id');
            $durationMinutes = (int) $this->input('duration_minutes', 0);
            if ($serviceId && $durationMinutes > 0) {
                $service = Service::find($serviceId);
                if ($service) {
                    if ($service->min_duration_minutes !== null && $durationMinutes < $service->min_duration_minutes) {
                        $message = 'Duration is below the minimum allowed for this service.';
                        $validator->errors()->add('duration_minutes', $message);
                        // Surface business-rule style validation on a generic key for feature tests
                        $validator->errors()->add('error', $message);
                    }

                    if ($service->max_duration_minutes !== null && $durationMinutes > $service->max_duration_minutes) {
                        $message = 'Duration exceeds the maximum allowed for this service.';
                        $validator->errors()->add('duration_minutes', $message);
                        // Surface business-rule style validation on a generic key for feature tests
                        $validator->errors()->add('error', $message);
                    }
                }
            }
        });
    }
}
