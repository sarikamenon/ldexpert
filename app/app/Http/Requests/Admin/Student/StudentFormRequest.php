<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class StudentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function baseRules(?int $ignoreUserId = null): array
    {
        $emailRule = Rule::unique('users', 'email');
        if ($ignoreUserId) {
            $emailRule = $emailRule->ignore($ignoreUserId);
        }

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', $emailRule],
            'gender' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'id_number' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', Rule::in(array_keys(UsTimezones::TIMEZONES))],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'parent_guardian_name' => ['nullable', 'string', 'max:255'],
            'parent_guardian_email' => ['nullable', 'email:rfc', 'max:255'],
            'parent_guardian_phone' => ['nullable', 'regex:/^[\d-]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(array_keys(UsStates::STATES))],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered in the system',
            'parent_guardian_phone.regex' => 'Phone number can only contain digits and dashes.',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'date_of_birth.after' => 'Date of birth must be after 1900-01-01',
        ];
    }
}
