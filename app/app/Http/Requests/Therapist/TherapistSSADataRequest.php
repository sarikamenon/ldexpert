<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistSSADataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'therapist';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(array_column(SSAStatus::cases(), 'value'))],
            'filter_student_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
