<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

use App\Enums\SchoolStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeSchoolStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(SchoolStatus::values())],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
