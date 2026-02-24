<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

use App\Enums\SchoolStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SchoolDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(SchoolStatus::values())],
        ];
    }
}
