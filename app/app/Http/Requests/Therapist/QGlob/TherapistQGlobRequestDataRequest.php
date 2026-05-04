<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist\QGlob;

use App\Enums\QGlobRequestStatus;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistQGlobRequestDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::THERAPIST;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter_status' => ['nullable', Rule::in(QGlobRequestStatus::values())],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
        ];
    }
}
