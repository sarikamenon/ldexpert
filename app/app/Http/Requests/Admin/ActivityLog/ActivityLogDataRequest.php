<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ActivityLog;

use Illuminate\Foundation\Http\FormRequest;

final class ActivityLogDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter_action' => ['nullable', 'string', 'max:64'],
            'filter_model_type' => ['nullable', 'string', 'max:128'],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
            'filter_search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
