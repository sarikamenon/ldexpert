<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Schedule;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Enums\RecurrenceType;
use App\Enums\SSAStatus;
use App\Enums\WeekDay;
use App\Http\Requests\Concerns\ValidatesWeekendScheduling;
use App\Models\ServiceSupportAgreement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreScheduleRequest extends FormRequest
{
    use ValidatesWeekendScheduling;

    public function __construct(
        private readonly StudentRepositoryInterface $studentRepository,
    ) {
        parent::__construct();
    }

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

        return [
            'therapist_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'therapist')],
            'ssa_id' => ['required', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'student_ids' => ['required', 'array', 'min:1', 'max:1'],
            'student_ids.*' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
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
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'therapist_id.required' => 'Please select a therapist.',
            'therapist_id.exists' => 'The selected therapist does not exist.',
            'duration_minutes.min' => 'Duration must be at least :min minutes.',
            'duration_minutes.max' => 'Duration may not be greater than :max minutes.',
            'location_details.required' => 'Please enter the location or meeting details for this session.',
            'recurrence_end_date.required_unless' => 'End date is required for recurring schedules.',
            'occurrence_dates.required_unless' => 'Occurrence dates are required for recurring schedules.',
            'weekly_days.required_if' => 'Please select at least one day of the week for a custom weekly schedule.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $therapistId = (int) $this->input('therapist_id');
            $ssaId = $this->input('ssa_id');
            /** @var array<int, int> $studentIds */
            $studentIds = is_array($this->input('student_ids', [])) ? array_map('intval', (array) $this->input('student_ids', [])) : [];

            if (! $therapistId || ! $ssaId) {
                return;
            }

            /** @var ServiceSupportAgreement|null $ssa */
            $ssa = ServiceSupportAgreement::find($ssaId);
            if (! $ssa) {
                $validator->errors()->add('ssa_id', 'SSA not found.');

                return;
            }

            if ($ssa->assigned_therapist_id !== $therapistId) {
                $validator->errors()->add('ssa_id', 'This SSA does not belong to the selected therapist.');
            }

            if ($ssa->status !== SSAStatus::ACTIVE) {
                $validator->errors()->add('ssa_id', 'You can only create schedules for active SSAs.');
            }

            foreach ($studentIds as $studentId) {
                if ($ssa->student_id !== $studentId) {
                    $validator->errors()->add('student_ids', 'All students must belong to the selected SSA.');
                    break;
                }
            }

            $recurrenceType = $this->input('recurrence_type');
            /** @var array<int, string> $occurrenceDates */
            $occurrenceDates = is_array($this->input('occurrence_dates', [])) ? (array) $this->input('occurrence_dates', []) : [];

            $schoolId = count($studentIds) > 0
                ? $this->studentRepository->getSchoolIdByUserId($studentIds[0])
                : null;

            $this->addWeekendSchedulingErrors(
                $validator,
                $this->schoolAllowsWeekendScheduling($schoolId),
                $this->input('schedule_date'),
                $this->input('weekly_days'),
                $recurrenceType && $recurrenceType !== RecurrenceType::NONE->value ? $occurrenceDates : null,
            );

            if ($recurrenceType && $recurrenceType !== RecurrenceType::NONE->value && count($occurrenceDates) > 0) {
                if (count(array_unique($occurrenceDates)) !== count($occurrenceDates)) {
                    $validator->errors()->add('occurrence_dates', 'Duplicate occurrence dates are not allowed.');
                }
            }
        });
    }
}
