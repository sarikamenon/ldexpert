<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class TherapistSessionLogDataRequest extends FormRequest
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
            'filter_student_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter_ssa_id' => ['nullable', 'integer', 'exists:service_support_agreements,id'],
            'filter_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
        ];
    }
}
