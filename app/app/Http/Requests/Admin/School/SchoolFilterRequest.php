<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

use App\Enums\SchoolStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SchoolFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(SchoolStatus::values())],
            'show_deactivated' => ['sometimes', 'boolean'],
        ];
    }
}
