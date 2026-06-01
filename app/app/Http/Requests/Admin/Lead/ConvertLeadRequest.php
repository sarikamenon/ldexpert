<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Enums\SchoolType;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $creatingSchool = $this->creatingSchool();

        return [
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9.\-]+$/', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'email:rfc', 'max:255'],
            'create_private_family' => ['sometimes', 'boolean'],
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'id_number' => [$this->isPrivateStudent() ? 'nullable' : 'required', 'string', 'max:50'],
            'timezone' => ['required', Rule::in(array_keys(UsTimezones::TIMEZONES))],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(array_keys(UsStates::STATES))],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'schedule_email' => ['nullable', 'email:rfc', 'max:255'],

            // New school/family fields (required when no existing school is picked).
            'family_name' => [Rule::requiredIf($creatingSchool), 'nullable', 'string', 'max:255', Rule::unique('schools', 'display_name')],
            'family_school_type' => [Rule::requiredIf($creatingSchool), 'nullable', Rule::in(SchoolType::values())],
            'family_state' => [Rule::requiredIf($creatingSchool), 'nullable', Rule::in(array_keys(UsStates::STATES))],
            'family_timezone' => [Rule::requiredIf($creatingSchool), 'nullable', Rule::in(array_keys(UsTimezones::TIMEZONES))],
            'family_address' => ['nullable', 'string'],
            'family_contact_first_name' => ['nullable', 'string', 'max:255'],
            'family_contact_last_name' => ['nullable', 'string', 'max:255'],
            'family_contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'family_contact_phone' => ['nullable', 'regex:/^[\d-]+$/'],
            'family_invoice_email' => ['nullable', 'email:rfc', 'max:255'],
            'family_is_auto_extend' => ['sometimes', 'boolean'],
            'family_non_billable_scheduling' => ['sometimes', 'boolean'],
            'family_allow_weekend_scheduling' => ['sometimes', 'boolean'],
            'family_external_emr_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Whether a new school/family will be created (no existing school picked). */
    public function creatingSchool(): bool
    {
        return ! $this->hasSchool();
    }

    /** Whether the new school/family should be flagged as a private family. */
    public function creatingPrivateFamily(): bool
    {
        return $this->boolean('create_private_family');
    }

    /** Whether an existing school was picked. */
    private function hasSchool(): bool
    {
        $schoolId = $this->input('school_id');

        return $schoolId !== null && $schoolId !== '';
    }

    /**
     * Mirror StudentFormRequest: a student is "private" when the picked school is private,
     * or when creating a new school flagged as a private family. A new normal school
     * (no existing school picked, checkbox unchecked) is NOT private.
     */
    private function isPrivateStudent(): bool
    {
        if ($this->creatingSchool()) {
            return $this->creatingPrivateFamily();
        }

        $school = School::find((int) $this->input('school_id'));

        return $school !== null && $school->is_private_student;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username can only contain letters, numbers, dots, and dashes.',
            'id_number.required' => 'Student ID is required for non-private schools/families.',
            'family_name.required' => 'Enter a name for the new school/family.',
            'family_name.unique' => 'A school or family with this name already exists.',
            'family_contact_phone.regex' => 'Phone number can only contain digits and dashes.',
        ];
    }
}
