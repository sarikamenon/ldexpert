<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
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
        return [
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9.\-]+$/', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'email:rfc', 'max:255'],
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'id_number' => ['required', 'string', 'max:50'],
            'timezone' => ['required', Rule::in(array_keys(UsTimezones::TIMEZONES))],
            'grade_level' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', Rule::in(array_keys(UsStates::STATES))],
            'zip_code' => ['required', 'string', 'max:20'],
            'schedule_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username can only contain letters, numbers, dots, and dashes.',
        ];
    }
}
