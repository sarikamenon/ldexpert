<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QGlob;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

final class EligibleStudentsQGlobRequestRequest extends FormRequest
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
            'therapist_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
