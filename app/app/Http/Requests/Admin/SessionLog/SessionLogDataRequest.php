<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SessionLog;

use App\Enums\SessionLogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SessionLogDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'filter_student_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter_therapist_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'filter_ssa_id' => ['nullable', 'integer', 'exists:service_support_agreements,id'],
            'filter_status' => ['nullable', Rule::in(SessionLogStatus::values())],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
        ];
    }
}
