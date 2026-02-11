<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTherapistActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin';
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:therapist_profiles,id'],
            'action' => ['required', 'string', Rule::in(['activate', 'deactivate', 'delete', 'export'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Please select at least one therapist.',
            'ids.*.exists' => 'One or more selected therapists do not exist.',
            'action.required' => 'Please specify an action to perform.',
            'action.in' => 'Invalid bulk action specified.',
        ];
    }
}
