<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ServiceAlias;

use App\Enums\SSAImportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreServiceAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(array_map(
                static fn (SSAImportType $s): string => $s->value,
                SSAImportType::aliasSources()
            ))],
            'external_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_aliases')->where(function ($query): void {
                    $query->where('source', $this->input('source'));
                }),
            ],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'external_name.unique' => 'This external name already exists for the selected source.',
        ];
    }
}
