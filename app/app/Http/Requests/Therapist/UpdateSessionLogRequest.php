<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Billing\Services\BillingEntryWindowService;
use App\Enums\RateType;
use App\Enums\SessionOutcome;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSessionLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Normalize notes by trimming leading/trailing whitespace
        if ($this->has('notes')) {
            $this->merge([
                'notes' => trim((string) $this->input('notes')),
            ]);
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

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $rules = [
            'student_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'ssa_id' => ['sometimes', 'integer', Rule::exists('service_support_agreements', 'id')],
            'service_id' => ['sometimes', 'integer', Rule::exists('services', 'id')],
            'session_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after:start_time'],
            'outcome' => ['sometimes', 'string', Rule::in(SessionOutcome::values())],
            'notes' => ['sometimes', 'string', 'min:20', 'max:5000'],
            'is_billable_therapist' => ['sometimes', 'boolean'],
            'is_billable_school' => ['sometimes', 'boolean'],
            'is_rate_override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'min:20', 'max:500'],
        ];

        // Conditional validation for rate override
        if ($this->boolean('is_rate_override')) {
            $rules['override_reason'][] = 'required';
            $rules['therapist_rate_type'] = ['required', 'string', Rule::in(RateType::values())];
            $rules['therapist_rate_amount'] = ['required', 'numeric', 'min:0'];
            $rules['therapist_billable_amount'] = ['required', 'numeric', 'min:0'];
            // School-side overrides are admin-only; keep therapist UI focused on provider amounts.
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'notes.min' => 'Session notes must be at least :min characters.',
            'end_time.after' => 'End time must be after start time.',
            'override_reason.required' => 'Override reason is required when rate is overridden.',
            'override_reason.min' => 'Override reason must be at least :min characters.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\SessionLog|null $sessionLog */
            $sessionLog = $this->route('sessionLog');

            if (! $sessionLog) {
                return;
            }

            // Validate session log is in draft status
            if (! $sessionLog->canEdit()) {
                $validator->errors()->add('status', 'Session log cannot be edited in its current status.');
            }

            // Validate billing entry window (hard block for therapists)
            $windowService = app(BillingEntryWindowService::class);
            $windowResult = $windowService->checkWindow($sessionLog->session_date);
            if (! $windowResult->isWithinWindow) {
                $validator->errors()->add(
                    'session_date',
                    "The billing window for this session's week closed on "
                    .Carbon::parse($windowResult->cutoff)->format('l, M j, Y \a\t g:i A')
                    .'. Session logs can no longer be edited for this date.'
                );
            }
        });
    }
}
