<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

use App\Constants\UsStates;
use App\Enums\LeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class LeadFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function baseRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'parent_guardian_name' => ['nullable', 'string', 'max:255'],
            'parent_guardian_email' => ['nullable', 'email:rfc', 'max:255'],
            'parent_guardian_phone' => ['nullable', 'regex:/^[\d-]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(array_keys(UsStates::STATES))],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', Rule::in(LeadSource::options())],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parent_guardian_phone.regex' => 'Phone number can only contain digits and dashes.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'date_of_birth.after' => 'Date of birth must be after 1900-01-01.',
        ];
    }
}
