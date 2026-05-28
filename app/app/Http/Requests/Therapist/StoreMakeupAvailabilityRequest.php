<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Time\UserTimezoneService;
use App\Enums\Role;
use App\Rules\NoMakeupAvailabilityScheduleOverlap;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMakeupAvailabilityRequest extends FormRequest
{
    public function __construct(private readonly UserTimezoneService $timezoneService)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->user();

        return $user->role === Role::THERAPIST;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        /** @var \App\Models\User $therapist */
        $therapist = $this->user();

        $date  = $this->input('availability_date', '');
        $start = $this->input('start_time', '');

        return [
            'availability_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
                new NoMakeupAvailabilityScheduleOverlap($therapist, (string) $date, (string) $start, $this->timezoneService),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'availability_date.required' => 'Please select a date.',
            'availability_date.after_or_equal' => 'Availability date must be today or in the future.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
