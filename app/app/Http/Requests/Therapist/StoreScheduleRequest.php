<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\RecurrenceType;
use App\Enums\SSAStatus;
use App\Enums\WeekDay;
use App\Http\Requests\Concerns\ValidatesWeekendScheduling;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreScheduleRequest extends FormRequest
{
    use ValidatesWeekendScheduling;

    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly SchoolCalendarService $calendarService,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    /** @return array<string, array<int, mixed>|string> */
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
            'schedule_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.config('session_minutes.min'),
                'max:'.config('session_minutes.max'),
            ],
            'recurrence_type' => ['required', Rule::in($recurrenceTypes)],
            'recurrence_end_date' => ['required_unless:recurrence_type,'.RecurrenceType::NONE->value, 'nullable', 'date', 'after:schedule_date'],
            'weekly_days' => ['required_if:recurrence_type,'.RecurrenceType::CUSTOM_WEEKLY->value, 'nullable', 'array', 'min:1'],
            'weekly_days.*' => [Rule::in(array_column(WeekDay::cases(), 'value'))],
            'occurrence_dates' => ['required_unless:recurrence_type,'.RecurrenceType::NONE->value, 'nullable', 'array', 'min:1'],
            'occurrence_dates.*' => ['required', 'date', 'after_or_equal:schedule_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'location_details' => ['required', 'string', 'max:2000'],
            'request_sub' => ['nullable', 'boolean'],
            'sub_reason' => ['required_if:request_sub,1', 'nullable', 'string', 'max:1000'],
            'sub_invitee_ids' => ['nullable', 'array', 'min:1'],
            'sub_invitee_ids.*' => ['integer', 'exists:users,id'],
            'makeup_request_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_minutes.required' => 'Duration is required.',
            'duration_minutes.min' => 'Duration must be at least :min minutes.',
            'duration_minutes.max' => 'Duration may not be greater than :max minutes.',
            'location_details.required' => 'Please enter the location or meeting details for this session.',
            'location_details.max' => 'Location/meeting details may not be greater than :max characters.',
            'recurrence_end_date.required_unless' => 'End date is required for recurring schedules.',
            'recurrence_end_date.after' => 'End date must be after the schedule start date.',
            'weekly_days.required_if' => 'Please select at least one day of the week for a custom weekly schedule.',
            'weekly_days.*.in' => 'Invalid day selected.',
            'occurrence_dates.required_unless' => 'Occurrence dates are required for recurring schedules.',
            'occurrence_dates.array' => 'Occurrence dates must be an array.',
            'occurrence_dates.min' => 'At least one occurrence date is required.',
            'occurrence_dates.*.required' => 'All occurrence dates must be filled.',
            'occurrence_dates.*.date' => 'Each occurrence date must be a valid date.',
            'occurrence_dates.*.after_or_equal' => 'Each occurrence date must be on or after the schedule start date.',
            'sub_reason.required_if' => 'Please provide a reason for the sub request.',
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

            // sub_invitee_ids is required when request_sub is true
            if ($this->boolean('request_sub')) {
                $inviteeIds = $this->input('sub_invitee_ids');
                if (empty($inviteeIds) || ! is_array($inviteeIds)) {
                    $validator->errors()->add('sub_invitee_ids', 'Please select at least one therapist to invite when requesting a sub.');
                }

                if ($this->input('recurrence_type') !== RecurrenceType::NONE->value) {
                    $validator->errors()->add('recurrence_type', 'Sub coverage can only be requested for single (non-recurring) sessions.');
                }
            }

            // Validate therapist has access to SSA and it's active
            if ($ssaId) {
                /** @var ServiceSupportAgreement|null $ssa */
                $ssa = ServiceSupportAgreement::find($ssaId);
                if (! $ssa) {
                    $validator->errors()->add('ssa_id', 'SSA not found.');

                    return;
                }

                if (! $this->scheduleRepository->validateTherapistAccessToSSA($therapist, (int) $ssaId)) {
                    $validator->errors()->add('ssa_id', 'You do not have access to this SSA.');
                }

                // Validate SSA is active
                if ($ssa->status !== SSAStatus::ACTIVE) {
                    $validator->errors()->add('ssa_id', 'You can only create schedules for active SSAs.');
                }
            }

            // Validate students belong to SSA if provided
            if ($ssaId && $studentCount > 0) {
                /** @var ServiceSupportAgreement|null $ssa */
                $ssa = ServiceSupportAgreement::find($ssaId);
                if ($ssa) {
                    foreach ($studentIdsArray as $studentId) {
                        if ($ssa->student_id !== (int) $studentId) {
                            $validator->errors()->add('student_ids', 'All students must belong to the selected SSA.');
                            break;
                        }
                    }
                }
            }

            // Validate service is available for the student via the SSA or as a common indirect service
            if ($serviceId && $ssaId && $studentCount > 0) {
                /** @var ServiceSupportAgreement|null $ssa */
                $ssa = ServiceSupportAgreement::find($ssaId);
                if ($ssa && $ssa->primary_service_id !== (int) $serviceId) {
                    $schoolId = $ssa->student?->studentProfile?->school_id;
                    $therapistProfileId = $therapist->therapistProfile?->id;
                    $isCommonIndirect = false;
                    if ($schoolId && $therapistProfileId) {
                        $isCommonIndirect = $this->serviceCatalogService
                            ->listCommonIndirectServices($therapistProfileId, $schoolId)
                            ->contains('id', (int) $serviceId);
                    }
                    if (! $isCommonIndirect) {
                        $validator->errors()->add('service_id', 'This service is not available for the selected SSA.');
                    }
                }
            }

            // Validate students are assigned to therapist
            if ($studentCount > 0) {
                if (! $this->scheduleRepository->validateTherapistAccessToStudents($therapist, $studentIdsArray)) {
                    $validator->errors()->add('student_ids', 'One or more students are not assigned to you.');
                }
            }

            // Validate occurrence dates for recurring schedules
            $recurrenceType = $this->input('recurrence_type');
            $occurrenceDates = $this->input('occurrence_dates', []);
            $occurrenceDatesArray = is_array($occurrenceDates) ? $occurrenceDates : [];

            $schoolIdForWeekend = $studentCount > 0
                ? $this->studentRepository->getSchoolIdByUserId((int) $studentIdsArray[0])
                : null;
            $allowsWeekend = $this->schoolAllowsWeekendScheduling($schoolIdForWeekend);

            $this->addWeekendSchedulingErrors(
                $validator,
                $allowsWeekend,
                $this->input('schedule_date'),
                $this->input('weekly_days'),
                $recurrenceType && $recurrenceType !== RecurrenceType::NONE->value ? $occurrenceDatesArray : null,
            );

            if ($recurrenceType && $recurrenceType !== RecurrenceType::NONE->value && count($occurrenceDatesArray) > 0) {
                $uniqueDates = array_unique($occurrenceDatesArray);
                if (count($uniqueDates) !== count($occurrenceDatesArray)) {
                    $validator->errors()->add('occurrence_dates', 'Duplicate occurrence dates are not allowed. Each occurrence must be on a unique date.');
                }
            }

            // Validate schedule and occurrence dates are not on holidays
            if ($studentCount > 0) {
                $schoolId = $this->studentRepository->getSchoolIdByUserId((int) $studentIdsArray[0]);

                if ($schoolId) {
                    $datesToCheck = array_filter(array_unique(array_merge(
                        [$this->input('schedule_date')],
                        is_array($occurrenceDates) ? $occurrenceDates : []
                    )));

                    if (count($datesToCheck) > 0) {
                        $dateObjects = array_map(static fn ($date) => Carbon::parse((string) $date), $datesToCheck);
                        $minDate = collect($dateObjects)->min();
                        $maxDate = collect($dateObjects)->max();

                        $holidayEvents = $this->calendarService->listHolidayEventsBySchoolAndRange($schoolId, $minDate, $maxDate);

                        if ($holidayEvents->isNotEmpty()) {
                            $holidayDates = [];
                            $holidayDateKeys = [];
                            foreach ($datesToCheck as $dateStr) {
                                $date = Carbon::parse((string) $dateStr)->format('Y-m-d');
                                $isHoliday = $holidayEvents->first(function ($event) use ($date) {
                                    return $event->start_date->format('Y-m-d') <= $date
                                        && $event->end_date->format('Y-m-d') >= $date;
                                });
                                if ($isHoliday) {
                                    $holidayDateKeys[] = $date;
                                    $holidayDates[] = Carbon::parse($date)->format('M d, Y');
                                }
                            }

                            if (count($holidayDates) > 0) {
                                $message = 'Scheduling is not allowed on school holidays: '.implode(', ', $holidayDates).'.';
                                $scheduleDateKey = $this->input('schedule_date');
                                if ($scheduleDateKey && in_array($scheduleDateKey, $holidayDateKeys, true)) {
                                    $validator->errors()->add('schedule_date', $message);
                                }
                                $occurrenceHolidayDates = array_filter(
                                    $holidayDateKeys,
                                    static fn ($date) => $date !== $scheduleDateKey
                                );
                                if (count($occurrenceHolidayDates) > 0) {
                                    $validator->errors()->add('occurrence_dates', $message);
                                }
                            }
                        }
                    }
                }
            }
        });
    }
}
