<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\TherapistPosition;
use App\Enums\TherapistTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TherapistFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function baseRules(?int $ignoreUserId = null): array
    {
        $personalEmailRule = Rule::unique('therapist_profiles', 'personal_email');
        if ($ignoreUserId) {
            // Ignore the therapist profile based on user_id
            $personalEmailRule = $personalEmailRule->where(function ($query) use ($ignoreUserId) {
                $query->where('user_id', '!=', $ignoreUserId);
            });
        }

        return [
            'employee_type' => ['required', Rule::in(EmployeeType::values())],
            'title' => ['required', Rule::in(TherapistTitle::values())],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'personal_email' => ['required', 'email:rfc', $personalEmailRule],
            'phone' => ['required', 'regex:/^\d{3}-\d{3}-\d{4}$/'],
            'ld_email' => ['nullable', 'email:rfc'],
            'address' => ['nullable', 'string'],
            'comments' => ['nullable', 'string'],
            'position' => ['required', Rule::in(TherapistPosition::values())],
            'state' => ['required', Rule::in(array_keys(UsStates::STATES))],
            'timezone' => ['required', Rule::in(array_keys(UsTimezones::TIMEZONES))],
            'manager_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn($query) => $query->where('role', Role::ADMIN->value)),
            ],
            'dob' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be in format 123-456-7890',
            'personal_email.unique' => 'This email is already registered in the system',
            'manager_id.exists' => 'Selected manager must be an admin user',
            'dob.before' => 'Date of birth must be in the past',
            'dob.after' => 'Date of birth must be after 1900',
        ];
    }
}
