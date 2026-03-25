<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QGlob;

use App\Enums\QGlobRequestStatus;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RespondQGlobRequestRequest extends FormRequest
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
            'decision' => ['required', Rule::in([QGlobRequestStatus::APPROVED->value, QGlobRequestStatus::REJECTED->value])],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
