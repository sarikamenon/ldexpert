<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateScheduleRequest extends FormRequest
{
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
            'location_details' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'billing_status' => ['nullable', Rule::in($billingStatuses)],
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
            }
        });
    }
}
