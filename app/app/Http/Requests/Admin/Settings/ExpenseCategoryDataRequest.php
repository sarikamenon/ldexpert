<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExpenseCategoryDataRequest extends FormRequest
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
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
