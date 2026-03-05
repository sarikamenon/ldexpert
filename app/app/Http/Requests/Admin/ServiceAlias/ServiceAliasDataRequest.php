<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ServiceAlias;

use App\Enums\SSAImportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ServiceAliasDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_source' => ['nullable', Rule::in(SSAImportType::values())],
        ];
    }
}
