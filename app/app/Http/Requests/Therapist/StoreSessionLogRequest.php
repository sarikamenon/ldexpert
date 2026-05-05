<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Billing\Services\BillingEntryWindowService;
use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
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
            /** @var ServiceSupportAgreement|null $ssa */
            $ssa = ServiceSupportAgreement::find((int) $ssaId);

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
            $durationMinutes = $startTime->diffInMinutes($endTime);

            $this->merge([
                'duration_minutes' => $durationMinutes,
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'ssa_id' => ['required', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'schedule_id' => ['nullable', 'integer', Rule::exists('schedules', 'id')],
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['required', 'date_format:Y-m-d H:i:s', 'after:start_time'],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.config('session_minutes.min'),
                'max:'.config('session_minutes.max'),
            ],
            'outcome' => ['required', 'string', Rule::in(SessionOutcome::values())],
            'notes' => ['required', 'string', 'min:20', 'max:5000'],
            'is_billable_therapist' => ['nullable', 'boolean'],
            'is_billable_school' => ['nullable', 'boolean'],
            // Therapists cannot override rates; this is reserved for admins.
            'is_rate_override' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
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
            'duration_minutes.required' => 'Duration is required.',
            'duration_minutes.min' => 'Duration must be at least :min minutes.',
            'duration_minutes.max' => 'Duration must not exceed :max minutes.',
            'end_time.after' => 'End time must be after start time.',
            'outcome.required' => 'Session outcome is required.',
            'outcome.in' => 'The selected session outcome is invalid.',
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
                /** @var \App\Models\ServiceSupportAgreement|null $ssa */
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
                    if ($session->lt($ssa->start_date) || ($ssa->end_date !== null && $session->gt($ssa->end_date))) {
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
                /** @var Schedule|null $schedule */
                $schedule = Schedule::find($scheduleId);
                if ($schedule && $schedule->therapist_id !== $therapist->id) {
                    $validator->errors()->add('schedule_id', 'You do not have access to this schedule.');
                }
            }

            // For standalone (non-scheduled) session logs:
            // - service must be an indirect service
            // - session date must be a past date
            $serviceId = $this->input('service_id');
            $sessionDate = $this->input('session_date');
            if (! $scheduleId) {
                if ($serviceId) {
                    /** @var Service|null $svc */
                    $svc = Service::find((int) $serviceId);
                    if ($svc && $svc->is_direct_service) {
                        $validator->errors()->add('service_id', 'Only indirect services can be selected for non-scheduled session logs.');
                    }
                }

                if ($sessionDate) {
                    $parsedDate = Carbon::parse((string) $sessionDate);
                    if ($parsedDate->isFuture()) {
                        $validator->errors()->add('session_date', 'Session date cannot be a future date for non-scheduled session logs.');
                    }
                }
            }

            // Validate session date is not a holiday
            $calendarService = app(SchoolCalendarService::class);
            $studentRepository = app(StudentRepositoryInterface::class);
            $schoolId = null;

            if ($scheduleId) {
                /** @var Schedule|null $schedule */
                $schedule = Schedule::find($scheduleId);
                $schoolId = $schedule->school_id
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

            // Validate active contracts cover the session date
            $sessionDate = $this->input('session_date');
            if ($sessionDate && $therapist->id) {
                $contractRule = new \App\Rules\SessionDateHasActiveContracts($therapist->id, $schoolId);
                $contractRule->validate('session_date', $sessionDate, function (string $message) use ($validator): void { // @phpstan-ignore argument.type
                    $validator->errors()->add('session_date', $message);
                });
            }

            // Validate billing entry window (hard block for therapists).
            // Use the therapist's TZ so the weekly cutoff aligns with where
            // the work was done (per CLAUDE.md UTC rules).
            $sessionDate = $this->input('session_date');
            if ($sessionDate) {
                /** @var \App\Models\User|null $therapistUser */
                $therapistUser = $this->user();
                $tz = app(UserTimezoneService::class)->resolveTimezone($therapistUser);
                $windowService = app(BillingEntryWindowService::class);
                $windowResult = $windowService->checkWindow(Carbon::parse((string) $sessionDate, $tz), null, $tz);
                if (! $windowResult->isWithinWindow) {
                    $validator->errors()->add(
                        'session_date',
                        "The billing window for this session's week closed on "
                        .Carbon::parse($windowResult->cutoff)->format('l, M j, Y \a\t g:i A')
                        .'. Session logs can no longer be created for this date.'
                    );
                }
            }

            // Validate duration within service limits (if available)
            $serviceId = $this->input('service_id');
            $durationMinutes = (int) $this->input('duration_minutes', 0);
            if ($serviceId && $durationMinutes > 0) {
                /** @var Service|null $service */
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
