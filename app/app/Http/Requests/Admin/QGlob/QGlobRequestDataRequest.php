<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QGlob;

use App\Enums\QGlobRequestStatus;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QGlobRequestDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::ADMIN;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter_therapist_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter_status' => ['nullable', Rule::in(QGlobRequestStatus::values())],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
        ];
    }
}
