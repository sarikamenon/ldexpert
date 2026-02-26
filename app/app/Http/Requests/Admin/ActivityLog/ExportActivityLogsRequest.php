<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ActivityLog;

use Illuminate\Foundation\Http\FormRequest;

class ExportActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin';
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:64'],
            'model_type' => ['nullable', 'string', 'max:128'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
