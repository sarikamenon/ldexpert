<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Enums\Role;
use App\Enums\SchoolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SchoolFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function baseRules(?int $ignoreId = null): array
    {
        $displayNameRule = Rule::unique('schools', 'display_name');
        if ($ignoreId) {
            $displayNameRule = $displayNameRule->ignore($ignoreId);
        }

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255', $displayNameRule],
            'address' => ['nullable', 'string'],
            'state' => ['required', Rule::in(array_keys(UsStates::STATES))],
            'timezone' => ['required', Rule::in(array_keys(UsTimezones::TIMEZONES))],
            'manager_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', Role::ADMIN->value)),
            ],
            'contact_first_name' => ['nullable', 'string', 'max:255'],
            'contact_last_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^[\d-]+$/'],
            'contact_email' => ['nullable', 'email:rfc,dns'],
            'invoice_email' => ['nullable', 'email:rfc,dns'],
            'school_type' => ['required', Rule::in(SchoolType::values())],
            'is_private_student' => ['sometimes', 'boolean'],
            'non_billable_scheduling' => ['sometimes', 'boolean'],
            'external_emr_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Phone number can only contain digits and dashes.',
        ];
    }
}
