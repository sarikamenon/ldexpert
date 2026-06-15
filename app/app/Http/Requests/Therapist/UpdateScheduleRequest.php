<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\WeekDay;
use App\Http\Requests\Concerns\ValidatesWeekendScheduling;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateScheduleRequest extends FormRequest
{
    use ValidatesWeekendScheduling;

    public function authorize(): bool
    {
        /** @var Schedule|null $schedule */
        $schedule = Schedule::find($this->route('id'));

        return $schedule && $schedule->therapist_id === $this->user()?->id;
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
            'ssa_id' => ['nullable', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'student_ids' => ['sometimes', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('role', 'student');
            })],
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
            'location_details' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'billing_status' => ['nullable', Rule::in($billingStatuses)],
            'sub_invitee_ids' => ['sometimes', 'array'],
            'sub_invitee_ids.*' => ['integer', Rule::exists('users', 'id')],
            'request_sub' => ['nullable', 'boolean'],
            'sub_reason' => ['required_if:request_sub,1', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_minutes.required' => 'Duration is required.',
            'duration_minutes.min' => 'Duration must be at least :min minutes.',
            'duration_minutes.max' => 'Duration may not be greater than :max minutes.',
            'schedule_date.after_or_equal' => 'Schedule date cannot be in the past.',
            'recurrence_end_date.after' => 'Recurrence end date must be after schedule date.',
            'sub_reason.required_if' => 'Please provide a reason for the sub request.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $therapist = $this->user();
            $ssaId = $this->input('ssa_id');
            $studentIds = $this->input('student_ids');

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

            // Validate students are assigned to therapist if provided
            if ($studentIds && is_array($studentIds)) {
                if (! $repository->validateTherapistAccessToStudents($therapist, array_map('intval', $studentIds))) {
                    $validator->errors()->add('student_ids', 'One or more students are not assigned to you.');
                }
            }

            // Require end date when a non-none recurrence type is submitted
            $recurrenceType = $this->input('recurrence_type');
            if ($recurrenceType && $recurrenceType !== 'none' && ! $this->input('recurrence_end_date')) {
                $validator->errors()->add('recurrence_end_date', 'An end date is required for recurring schedules.');
            }

            // Require at least one day selected for custom_weekly
            if ($recurrenceType === 'custom_weekly') {
                $weeklyDays = $this->input('weekly_days');
                if (! is_array($weeklyDays) || count($weeklyDays) === 0) {
                    $validator->errors()->add('weekly_days', 'Please select at least one day for the custom weekly schedule.');
                }
            }

            // sub_invitee_ids is required when request_sub is true
            if ($this->boolean('request_sub')) {
                $inviteeIds = $this->input('sub_invitee_ids');
                if (! is_array($inviteeIds) || count($inviteeIds) === 0) {
                    $validator->errors()->add('sub_invitee_ids', 'Please select at least one therapist to invite when requesting a sub.');
                }
            }

            /** @var Schedule|null $schedule */
            $schedule = Schedule::find($this->route('id'));
            if ($schedule) {
                $calendarService = app(SchoolCalendarService::class);
                $studentRepository = app(StudentRepositoryInterface::class);
                $schoolId = $schedule->school_id
                    ?? $studentRepository->getSchoolIdByUserId((int) $schedule->student_id);

                $scheduleDate = $this->input('schedule_date');
                if ($schoolId && $scheduleDate) {
                    $date = Carbon::parse((string) $scheduleDate);
                    if ($calendarService->isHolidayDate((int) $schoolId, $date)) {
                        $validator->errors()->add(
                            'schedule_date',
                            'Scheduling is not allowed on school holidays.'
                        );
                    }
                }

                $occurrenceDatesInput = $this->input('occurrence_dates');

                $this->addWeekendSchedulingErrors(
                    $validator,
                    $this->schoolAllowsWeekendScheduling($schoolId ? (int) $schoolId : null),
                    $scheduleDate,
                    $this->input('weekly_days'),
                    is_array($occurrenceDatesInput) ? $occurrenceDatesInput : null,
                );
            }
        });
    }
}
