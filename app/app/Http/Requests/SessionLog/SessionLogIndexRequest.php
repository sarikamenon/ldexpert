<?php

declare(strict_types=1);

namespace App\Http\Requests\SessionLog;

use App\Enums\SessionLogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SessionLogIndexRequest extends FormRequest
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
            'school_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'therapist_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'ssa_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(SessionLogStatus::values())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
