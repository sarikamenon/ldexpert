<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $userRole = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        return in_array($userRole, [Role::THERAPIST, Role::ADMIN], true);
    }

    public function rules(): array
    {
        $routeParam = $this->route('user');
        $studentId = is_object($routeParam) ? ($routeParam->id ?? null) : $routeParam;

        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $studentId],

            // Name fields
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],

            // School and ID
            'school' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255'],

            // Timezone and Gender
            'timezone' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],

            // Address fields
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],

            // Parent/Guardian information
            'parent_guardian_name' => ['nullable', 'string', 'max:255'],
            'parent_guardian_email' => ['nullable', 'email', 'max:255'],
            'parent_guardian_phone' => ['nullable', 'string', 'max:255'],

            // Additional fields
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'grade_level' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
