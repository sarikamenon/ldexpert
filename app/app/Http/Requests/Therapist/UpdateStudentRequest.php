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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $studentId],
            'date_of_birth' => ['nullable', 'date'],
            'grade_level' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
